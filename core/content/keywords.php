<?php
/**
 * Ecod Theme · 关键词内链
 *
 * 把正文中的指定关键词替换为链接（内部词库 + 分类 / 标签名自动内链）。
 * 走 DOM 文本节点替换：只动纯文本，绝不触碰 <a>/<code>/<pre>/标题/按钮
 * 内部的文字，杜绝老式正则方案（Keywords 插件）的三类缺陷：
 *   1. 未 preg_quote（关键词含 . + ( ) 时误匹配甚至崩正则）—— 本方案用
 *      mb_stripos 字面匹配，天然免疫元字符；
 *   2. 正则跨越标签边界（命中属性值 / 代码块）—— 只遍历文本节点；
 *   3. 二次命中已生成的锚文本 —— 遍历顺序天然保证新插入的 <a> 不再参与。
 *
 * 配置（后台「站长 → 收录与性能」）：
 *   EKeywords      总开关
 *   EKeywordsList  词库，每行「关键词|链接|flags|限次」，flags 可空：
 *                  n=nofollow，b=本窗口打开（默认新窗口）；限次缺省用 EKeywordsLimit
 *   EKeywordsLimit 每个词在单篇内最多替换次数（默认 1）
 *   EKeywordsAuto  分类 / 标签名自动内链：off / cats / tags / both
 *
 * 调用点：post.php / page.php / sites.php 管线，必须位于
 * shortcode / oembed 之后、lazy_images / go_rewrite 之前——
 * 放前面会破坏短代码参数，放后面会嵌套 go_rewrite 生成的 <a>。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 词表（请求内缓存）
 *
 * @return array<int, array{w:string,u:string,rel:string,blank:bool,limit:int}> 长词优先
 */
function ecod_keywords_terms(): array
{
    static $terms = null;
    if ($terms !== null) {
        return $terms;
    }

    $terms = [];
    if (ecod_option('EKeywords', 'off') !== 'on') {
        return $terms;
    }

    $limit = max(1, (int) ecod_option('EKeywordsLimit', '1'));
    $raw = (string) ecod_option('EKeywordsList', '');

    foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $word = (string) ($parts[0] ?? '');
        $href = (string) ($parts[1] ?? '');
        if ($word === '' || $href === '') {
            continue;
        }
        $flags = (string) ($parts[2] ?? '');
        $own = (int) ($parts[3] ?? 0);

        $terms[] = [
            'w'     => $word,
            'u'     => $href,
            'rel'   => (stripos($flags, 'n') !== false) ? 'nofollow' : '',
            'blank' => (stripos($flags, 'b') !== false),
            'limit' => $own > 0 ? $own : $limit,
        ];
    }

    /* 分类 / 标签名自动内链：链接取 metas，flags 跟全局（内链默认不带 nofollow） */
    $auto = (string) ecod_option('EKeywordsAuto', 'off');
    if ($auto !== 'off') {
        try {
            $types = [];
            if ($auto === 'cats' || $auto === 'both') {
                $types[] = 'category';
            }
            if ($auto === 'tags' || $auto === 'both') {
                $types[] = 'tag';
            }
            foreach ($types as $type) {
                $rows = \Typecho\Db::get()->fetchAll(
                    \Typecho\Db::get()->select('name', 'slug', 'type', 'count')
                        ->from('table.metas')
                        ->where('type = ?', $type)
                        ->where('count > ?', 0)
                        ->order('count', \Typecho\Db::SORT_DESC)
                        ->limit($type === 'tag' ? 200 : 100)
                );
                foreach ($rows as $row) {
                    $name = trim((string) $row['name']);
                    if ($name === '' || mb_strlen($name, 'UTF-8') < 2) {
                        continue;
                    }
                    $link = '';
                    try {
                        $link = \Typecho\Router::url($type, $row, \Widget\Options::alloc()->index);
                    } catch (\Throwable $e) {
                        continue;
                    }
                    $terms[] = ['w' => $name, 'u' => $link, 'rel' => '', 'blank' => false, 'limit' => 1];
                }
            }
        } catch (\Throwable $e) {
        }
    }

    if ($terms) {
        /* 长词优先：短的词不能抢在长词前面命中 */
        usort($terms, static function (array $a, array $b): int {
            return mb_strlen($b['w'], 'UTF-8') <=> mb_strlen($a['w'], 'UTF-8');
        });
    }
    return $terms;
}

/**
 * 节点是否处于禁止替换的祖先元素内
 */
function ecod_keywords_skipped(\DOMNode $node): bool
{
    static $skip = [
        'a' => true, 'code' => true, 'pre' => true, 'kbd' => true,
        'script' => true, 'style' => true, 'textarea' => true,
        'button' => true, 'figcaption' => true, 'cite' => true, 'mark' => true,
        'h1' => true, 'h2' => true, 'h3' => true, 'h4' => true, 'h5' => true, 'h6' => true,
    ];
    for ($p = $node->parentNode; $p !== null; $p = $p->parentNode) {
        if ($p instanceof \DOMElement && isset($skip[strtolower($p->tagName)])) {
            return true;
        }
    }
    return false;
}

/**
 * 在一个文本节点内完成全部替换（递归处理剩余文本）
 *
 * @param array<int, array> $terms  长词优先词表
 * @param array<string, int> $hits  每词已替换次数（引用穿透）
 * @param \DOMNode $node            当前文本节点
 */
function ecod_keywords_text_node(string $text, array $terms, array &$hits, \DOMDocument $doc): array
{
    /* 找出本文本内最早命中的词（平手取更长的词） */
    $best = null;
    foreach ($terms as $idx => $term) {
        if ($hits[$term['w']] >= $term['limit']) {
            continue;
        }
        $pos = mb_stripos($text, $term['w'], 0, 'UTF-8');
        if ($pos === false) {
            continue;
        }
        if ($best === null
            || $pos < $best['pos']
            || ($pos === $best['pos'] && mb_strlen($term['w'], 'UTF-8') > mb_strlen($terms[$best['idx']]['w'], 'UTF-8'))) {
            $best = ['pos' => $pos, 'idx' => $idx];
        }
    }
    if ($best === null) {
        return [];
    }

    $term = $terms[$best['idx']];
    $pos = $best['pos'];
    $len = mb_strlen($term['w'], 'UTF-8');
    $hits[$term['w']]++;

    $nodes = [];
    $before = mb_substr($text, 0, $pos, 'UTF-8');
    $after = mb_substr($text, $pos + $len, null, 'UTF-8');
    if ($before !== '') {
        $nodes[] = $doc->createTextNode($before);
    }

    $rel = $term['rel'];
    $isLocal = function_exists('ecod_go_is_local') && ecod_go_is_local($term['u']);
    if ($term['u'] !== '' && !$isLocal && strpos($rel, 'nofollow') === false) {
        $rel = trim('nofollow noopener ' . $rel);
    }
    $a = $doc->createElement('a');
    /* setAttribute 走 DOM 转义，比手拼字符串更稳 */
    $a->setAttribute('href', $term['u']);
    $a->setAttribute('title', $term['w']);
    if ($rel !== '') {
        $a->setAttribute('rel', $rel);
    }
    if (!$term['blank']) {
        $a->setAttribute('target', '_blank');
    }
    $a->appendChild($doc->createTextNode($term['w']));
    $nodes[] = $a;

    if ($after !== '') {
        /* after 无命中时返回空数组（apply 遍历语义），这里必须保留原文 */
        $rest = ecod_keywords_text_node($after, $terms, $hits, $doc);
        $nodes = array_merge($nodes, $rest !== [] ? $rest : [$doc->createTextNode($after)]);
    }
    return $nodes;
}

/**
 * 正文关键词内链
 *
 * libxml 对 script/style/svg 内容的序列化存在实体坑，先把这三类块
 * 摘出来放占位符，DOM 处理完再原样放回。
 */
function ecod_keywords_apply(string $html): string
{
    $terms = ecod_keywords_terms();
    if ($terms === [] || $html === '' || strpos($html, '<') === false) {
        return $html;
    }

    /* 摘出 script / style / svg 块（大小写 + 自闭合变体统一处理） */
    $vault = [];
    $html = preg_replace_callback(
        '#<(script|style|svg)\b[^>]*>.*?</\1\s*>#is',
        static function (array $m) use (&$vault): string {
            $key = 'ECODKWVAULT' . count($vault) . 'END';
            $vault[$key] = $m[0];
            return $key;
        },
        $html
    ) ?? $html;

    $inner = static function (\DOMDocument $doc): string {
        $out = '';
        foreach ($doc->getElementsByTagName('body')->item(0)?->childNodes ?? [] as $child) {
            $out .= $doc->saveHTML($child);
        }
        return $out;
    };

    $doc = new \DOMDocument();
    libxml_use_internal_errors(true);
    $ok = $doc->loadHTML(
        '<?xml encoding="UTF-8"><body>' . $html . '</body>',
        LIBXML_NOERROR | LIBXML_NOWARNING
    );
    libxml_clear_errors();

    if (!$ok) {
        /* 还原占位符后原样返回，绝不吞正文 */
        return str_replace(array_keys($vault), array_values($vault), $html);
    }

    $hits = [];
    foreach ($terms as $term) {
        $hits[$term['w']] = 0;
    }

    /* 快照所有文本节点再处理（替换会改变树结构，不能边遍历边改） */
    $xpath = new \DOMXPath($doc);
    $texts = [];
    foreach ($xpath->query('//body//text()') ?: [] as $node) {
        $texts[] = $node;
    }

    foreach ($texts as $node) {
        $text = (string) $node->nodeValue;
        if (trim($text) === '' || ecod_keywords_skipped($node)) {
            continue;
        }
        $replacement = ecod_keywords_text_node($text, $terms, $hits, $doc);
        if ($replacement === []) {
            continue;
        }
        $parent = $node->parentNode;
        if ($parent === null) {
            continue;
        }
        foreach ($replacement as $new) {
            $parent->insertBefore($new, $node);
        }
        $parent->removeChild($node);
    }

    $out = $inner($doc);
    return str_replace(array_keys($vault), array_values($vault), $out);
}
