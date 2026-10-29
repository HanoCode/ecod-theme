<?php
/**
 * Ecod Theme · AI 智能摘要
 *
 * 后台「AI 助手」配置接入后，文章页通过 api.php?action=ai 异步生成摘要。
 * 结果按「文章内容 hash」缓存在 options 表，正文未变就不重复请求。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * AI 摘要是否可用（总开关 + 必要配置齐全）
 */
function ecod_ai_enabled(): bool
{
    if (ecod_option('EAI', 'off') !== 'on') {
        return false;
    }
    return trim((string) ecod_option('EAIKey', '')) !== ''
        && trim((string) ecod_option('EAIModel', '')) !== '';
}

/**
 * 各服务商的默认接口地址（OpenAI 兼容协议）
 */
function ecod_ai_endpoint(): string
{
    $custom = trim((string) ecod_option('EAIEndpoint', ''));
    if ($custom !== '') {
        return $custom;
    }
    switch ((string) ecod_option('EAIProvider', 'openai')) {
        case 'deepseek':
            return 'https://api.deepseek.com/v1/chat/completions';
        case 'qwen':
            return 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';
        case 'zhipu':
            return 'https://open.bigmodel.cn/api/paas/v4/chat/completions';
        default:
            return '';
    }
}

/**
 * 正文纯文本（截断到模型可接受的长度）
 */
function ecod_ai_source_text(string $raw): string
{
    $raw = preg_replace('/^<!--markdown-->/', '', $raw);
    /* 去掉短代码块，避免播放列表这类非叙述内容干扰摘要 */
    $raw = preg_replace('#\[[a-z_-]+[^\]]*\].*?\[/[a-z_-]+\]#is', ' ', $raw);
    $raw = preg_replace('#\[([a-z_-]+)[^\]]*\]#is', ' ', $raw);
    $text = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES, 'UTF-8'));
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return mb_substr($text, 0, 6000, 'UTF-8');
}

/**
 * 取摘要（带缓存）
 *
 * @return array{ok: bool, summary?: string, cached?: bool, error?: string}
 */
function ecod_ai_summary(int $cid): array
{
    if (!ecod_ai_enabled()) {
        return ['ok' => false, 'error' => 'AI 摘要未开启或配置不完整'];
    }

    $db = \Typecho\Db::get();
    $row = $db->fetchRow(
        $db->select('text', 'title')->from('table.contents')
            ->where('cid = ?', $cid)->where('type = ?', 'post')->where('status = ?', 'publish')
    );
    if (!$row) {
        return ['ok' => false, 'error' => '文章不存在'];
    }

    $source = ecod_ai_source_text((string) $row['text']);
    if (mb_strlen($source, 'UTF-8') < 80) {
        return ['ok' => false, 'error' => '正文太短，无需摘要'];
    }

    $hash = md5($source);
    $cacheKey = 'theme:ecod_ai_' . $cid;

    /* 缓存命中判断要跑两次：进锁前一次、进锁后一次（防并发重复调 API） */
    $readCache = static function () use ($cacheKey, $hash): ?array {
        try {
            $cacheRaw = (string) \Utils\Helper::options()->{$cacheKey};
        } catch (\Throwable $e) {
            return null;
        }
        $cache = is_string($cacheRaw) && $cacheRaw !== '' ? json_decode($cacheRaw, true) : null;
        if (is_array($cache) && ($cache['hash'] ?? '') === $hash && ($cache['summary'] ?? '') !== '') {
            return ['ok' => true, 'summary' => (string) $cache['summary'], 'cached' => true];
        }
        return null;
    };

    $hit = $readCache();
    if ($hit !== null) {
        return $hit;
    }

    /* 生成锁：同一篇的并发 miss 只放一个请求去调模型，其余直接返回，
     * 前端本次隐藏区块，下一次页面加载自然命中缓存 */
    $lockFh = @fopen(sys_get_temp_dir() . '/ecod-ai-' . md5($cacheKey) . '.lock', 'c');
    $gotLock = false;
    if ($lockFh) {
        $gotLock = @flock($lockFh, LOCK_EX | LOCK_NB);
        if (!$gotLock) {
            fclose($lockFh);
            return ['ok' => false, 'error' => '正在生成，请稍后刷新重试'];
        }
        $hit = $readCache();
        if ($hit !== null) {
            flock($lockFh, LOCK_UN);
            fclose($lockFh);
            return $hit;
        }
    }
    $unlock = static function () use ($lockFh): void {
        if ($lockFh) {
            @flock($lockFh, LOCK_UN);
            @fclose($lockFh);
        }
    };

    $endpoint = ecod_ai_endpoint();
    $key = trim((string) ecod_option('EAIKey', ''));
    $model = trim((string) ecod_option('EAIModel', ''));
    $title = (string) $row['title'];
    $prompt = trim((string) ecod_option('EAIPrompt', ''));

    $system = $prompt !== '' ? $prompt
        : '你是一位中文博客编辑。请为下面的文章写一段 80~140 字的摘要：'
        . "概括核心观点与结论，语言平实自然，不要出现「本文」「作者」以外的套话，"
        . "不要使用 Markdown 标记，直接输出摘要正文。";

    $payload = json_encode([
        'model'       => $model,
        'temperature' => 0.3,
        'max_tokens'  => 300,
        'messages'    => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => "标题：{$title}\n\n正文：\n{$source}"],
        ],
    ], JSON_UNESCAPED_UNICODE);

    if (!function_exists('curl_init')) {
        $unlock();
        return ['ok' => false, 'error' => '服务器缺少 curl 扩展'];
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($errno || !is_string($body)) {
        $unlock();
        return ['ok' => false, 'error' => '接口请求失败'];
    }

    $data = json_decode($body, true);
    $summary = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
    if ($summary === '') {
        $unlock();
        return ['ok' => false, 'error' => '接口未返回摘要'];
    }

    /* 写缓存：正文不变就不重复请求（options.name 为主键，先 update 后 insert） */
    try {
        $cacheValue = json_encode(['hash' => $hash, 'summary' => $summary, 'at' => time()], JSON_UNESCAPED_UNICODE);
        $updated = $db->query(
            $db->update('table.options')->rows(['value' => $cacheValue])->where('name = ?', $cacheKey)
        );
        if (!$updated) {
            $db->query(
                $db->insert('table.options')->rows(['name' => $cacheKey, 'value' => $cacheValue, 'user' => 0])
            );
        }
    } catch (\Throwable $e) {
        /* 缓存失败不影响本次返回 */
    }

    $unlock();
    return ['ok' => true, 'summary' => $summary, 'cached' => false];
}
