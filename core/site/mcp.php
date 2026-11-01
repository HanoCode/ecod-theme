<?php
/**
 * Ecod Theme · MCP Server（F11）
 *
 * 把「搜文章 / 读文章 / 站点信息」暴露成 MCP 工具，读者可以在 Claude、
 * Cursor 之类的客户端里直接查本站。走 api.php?action=mcp 单入口，
 * 不新增对外端口、不改 nginx 白名单。
 *
 * 协议：MCP Streamable HTTP 的「POST + JSON 响应」最小实现
 *   POST  {"jsonrpc":"2.0","id":1,"method":"initialize",...}
 *   支持 initialize / notifications/initialized / ping / tools/list / tools/call
 *   也接受 JSON-RPC 批量数组（逐个回包）
 *
 * 安全：
 *   - 只读，且只返回已发布、无密码的文章；
 *   - 正文一律走 ecod_ai_text_allowed()，付费文章只给试读段（与 AI 同一条门禁）；
 *   - 后台可配 Token（Authorization: Bearer 或 ?key=），留空则公开只读。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-11-01
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/** MCP 服务是否可用（AI 总开关 + 独立开关） */
function ecod_mcp_enabled(): bool
{
    return ecod_ai_enabled() && (string) ecod_option('EAIMcp', 'off') === 'on';
}

/** 访问令牌（留空 = 公开只读） */
function ecod_mcp_token(): string
{
    return trim((string) ecod_option('EAIMcpToken', ''));
}

/** 鉴权：配了令牌就必须带对；没配则放行 */
function ecod_mcp_auth_ok(): bool
{
    $expect = ecod_mcp_token();
    if ($expect === '') {
        return true;
    }

    $given = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $given = (string) $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $given = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        foreach ((array) apache_request_headers() as $k => $v) {
            if (strtolower((string) $k) === 'authorization') {
                $given = (string) $v;
                break;
            }
        }
    }
    if (stripos($given, 'bearer ') === 0) {
        $given = trim(substr($given, 7));
    }
    if ($given === '' && isset($_GET['key'])) {
        $given = (string) $_GET['key'];
    }

    return $given !== '' && hash_equals($expect, $given);
}

/** 只设状态码（通知类请求回 202，无响应体） */
function ecod_mcp_status(int $status): void
{
    if (function_exists('ecod_http_status')) {
        ecod_http_status($status);
    } else {
        http_response_code($status);
    }
}

/** 输出 JSON-RPC 结果并结束 */
function ecod_mcp_emit($payload, int $status = 200): void
{
    /* 走主题统一的状态码函数：Typecho\Response 里也存了一份状态，
       只用 http_response_code() 会被它在发头时覆盖回 200 */
    if (function_exists('ecod_http_status')) {
        ecod_http_status($status);
    } else {
        http_response_code($status);
    }
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** 单个请求的响应体（成功） */
function ecod_mcp_ok($id, $result): array
{
    return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
}

/** 单个请求的响应体（错误） */
function ecod_mcp_err($id, int $code, string $message): array
{
    return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
}

/**
 * 工具清单（tools/list）
 *
 * @return array<int,array<string,mixed>>
 */
function ecod_mcp_tools(): array
{
    return [
        [
            'name'        => 'site_info',
            'description' => '站点概览：名称、简介、文章数、分类与最近更新。先调它了解这个站有什么。',
            'inputSchema' => ['type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false],
        ],
        [
            'name'        => 'search_posts',
            'description' => '按关键词搜索站内已发布文章，返回标题、日期与永久链接（最多 10 条）。',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => '检索词，可用空格分隔多个词'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => 5],
                ],
                'required'   => ['query'],
                'additionalProperties' => false,
            ],
        ],
        [
            'name'        => 'get_post',
            'description' => '读取一篇文章的正文与元信息。可用 cid 或 slug 定位；付费文章只返回试读段。',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'cid'  => ['type' => 'integer', 'description' => '文章 ID'],
                    'slug' => ['type' => 'string', 'description' => '文章别名（URL 末段）'],
                ],
                'additionalProperties' => false,
            ],
        ],
        [
            'name'        => 'list_posts',
            'description' => '按发布时间倒序列出文章（可分页、可按分类过滤）。',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'limit'    => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'default' => 10],
                    'offset'   => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                    'category' => ['type' => 'string', 'description' => '分类名（可选）'],
                ],
                'additionalProperties' => false,
            ],
        ],
    ];
}

/**
 * 文章永久链接（Router 需要 slug/type/created，取不到的返回空）
 */
function ecod_mcp_url(array $row): string
{
    try {
        return ecod_post_url($row);
    } catch (\Throwable $e) {
        return '';
    }
}

/** 工具：site_info */
function ecod_mcp_tool_site(): array
{
    return ecod_ai_site_brief(8);
}

/** 工具：search_posts */
function ecod_mcp_tool_search(array $args): array
{
    $query = trim((string) ($args['query'] ?? ''));
    $limit = max(1, min(10, (int) ($args['limit'] ?? 5)));
    if (mb_strlen($query, 'UTF-8') < 2) {
        return ['error' => 'query 至少 2 个字'];
    }

    $terms = preg_split('/[\s,，。？?！!、；;：:]+/u', $query) ?: [];
    $terms = array_values(array_filter(array_map('trim', $terms), static function ($t) {
        return mb_strlen($t, 'UTF-8') >= 2;
    }));
    if (!$terms) {
        $terms = [$query];
    }

    $rows = ecod_ai_search_posts($terms, $limit);
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'cid'   => (int) $row['cid'],
            'title' => (string) $row['title'],
            'date'  => date('Y-m-d', (int) $row['created']),
            'url'   => ecod_mcp_url($row),
        ];
    }

    return ['query' => $query, 'terms' => $terms, 'count' => count($out), 'posts' => $out];
}

/** 工具：get_post */
function ecod_mcp_tool_post(array $args): array
{
    $cid = (int) ($args['cid'] ?? 0);
    $slug = trim((string) ($args['slug'] ?? ''));

    try {
        $db = \Typecho\Db::get();
        $select = $db->select('cid', 'title', 'slug', 'created', 'modified', 'type', 'status', 'text')
            ->from('table.contents')
            ->where('type = ?', 'post')->where('status = ?', 'publish')
            ->where('created < ?', time())
            ->where('(password IS NULL OR password = ?)', '')
            ->limit(1);
        if ($cid > 0) {
            $select->where('cid = ?', $cid);
        } elseif ($slug !== '') {
            $select->where('slug = ?', $slug);
        } else {
            return ['error' => '需要 cid 或 slug'];
        }
        $row = $db->fetchRow($select);
    } catch (\Throwable $e) {
        return ['error' => '查询失败'];
    }

    if (!$row) {
        return ['error' => '没有这篇文章（可能未发布、设了密码或已删除）'];
    }

    $postCid = (int) $row['cid'];
    $tier = ecod_ai_access_tier($postCid, 0);
    $text = ecod_ai_text_allowed($postCid, 0);

    $tags = [];
    $cats = [];
    try {
        $metas = $db->fetchAll(
            "SELECT m.name, m.type FROM {$db->getPrefix()}metas m
               JOIN {$db->getPrefix()}relationships r ON r.mid = m.mid
              WHERE r.cid = {$postCid}"
        );
        foreach ((array) $metas as $meta) {
            if ($meta['type'] === 'tag') {
                $tags[] = (string) $meta['name'];
            } elseif ($meta['type'] === 'category') {
                $cats[] = (string) $meta['name'];
            }
        }
    } catch (\Throwable $e) {
        /* 标签取不到不影响正文 */
    }

    return [
        'cid'       => $postCid,
        'title'     => (string) $row['title'],
        'slug'      => (string) $row['slug'],
        'url'       => ecod_mcp_url($row),
        'date'      => date('Y-m-d', (int) $row['created']),
        'modified'  => date('Y-m-d', (int) $row['modified']),
        'categories'=> $cats,
        'tags'      => $tags,
        'paid'      => $tier !== 'full',
        'text'      => $text,
        'note'      => $tier !== 'full' ? '这是付费文章的试读段，完整内容需在站内购买或开通会员。' : '',
    ];
}

/** 工具：list_posts */
function ecod_mcp_tool_list(array $args): array
{
    $limit = max(1, min(20, (int) ($args['limit'] ?? 10)));
    $offset = max(0, (int) ($args['offset'] ?? 0));
    $category = trim((string) ($args['category'] ?? ''));

    try {
        $db = \Typecho\Db::get();
        $prefix = $db->getPrefix();
        $where = "c.type = 'post' AND c.status = 'publish' AND c.created < " . time()
            . " AND (c.password IS NULL OR c.password = '')";

        if ($category !== '') {
            $mid = $db->fetchRow(
                $db->select('mid')->from('table.metas')
                    ->where('type = ?', 'category')->where('name = ?', $category)->limit(1)
            );
            if (!$mid) {
                return ['error' => '没有这个分类'];
            }
            $where .= ' AND c.cid IN (SELECT cid FROM ' . $prefix . 'relationships WHERE mid = ' . (int) $mid['mid'] . ')';
        }

        $rows = $db->fetchAll(
            "SELECT c.cid, c.title, c.slug, c.created, c.type FROM {$prefix}contents c
              WHERE {$where} ORDER BY c.created DESC LIMIT {$limit} OFFSET {$offset}"
        );
    } catch (\Throwable $e) {
        return ['error' => '查询失败'];
    }

    $out = [];
    foreach ((array) $rows as $row) {
        $out[] = [
            'cid'   => (int) $row['cid'],
            'title' => (string) $row['title'],
            'date'  => date('Y-m-d', (int) $row['created']),
            'url'   => ecod_mcp_url($row),
        ];
    }

    return ['count' => count($out), 'offset' => $offset, 'posts' => $out];
}

/**
 * 执行一个工具调用，返回 MCP 的 content 数组
 *
 * @return array{content:array,isError:bool}
 */
function ecod_mcp_call(string $name, array $args): array
{
    switch ($name) {
        case 'site_info':
            $data = ecod_mcp_tool_site();
            break;
        case 'search_posts':
            $data = ecod_mcp_tool_search($args);
            break;
        case 'get_post':
            $data = ecod_mcp_tool_post($args);
            break;
        case 'list_posts':
            $data = ecod_mcp_tool_list($args);
            break;
        default:
            return [
                'content' => [['type' => 'text', 'text' => '未知工具：' . $name]],
                'isError' => true,
            ];
    }

    $isError = isset($data['error']);
    return [
        'content' => [['type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]],
        'isError' => $isError,
    ];
}

/** 处理单个 JSON-RPC 请求 */
function ecod_mcp_dispatch(array $req): array
{
    $id = $req['id'] ?? null;
    $method = (string) ($req['method'] ?? '');

    if ($method === '') {
        return ecod_mcp_err($id, -32600, 'Invalid Request');
    }

    /* 通知类：不需要 result（调用方按 202 处理） */
    if (strpos($method, 'notifications/') === 0) {
        return ['__notify' => true];
    }

    switch ($method) {
        case 'initialize':
            return ecod_mcp_ok($id, [
                'protocolVersion' => (string) ($req['params']['protocolVersion'] ?? '2024-11-05'),
                'capabilities'    => ['tools' => ['listChanged' => false]],
                'serverInfo'      => ['name' => 'ecod-blog', 'version' => '1.0.0'],
                'instructions'    => '这是一个 Typecho 博客的只读 MCP：先用 site_info 了解站点，'
                    . '用 search_posts 找文章，再用 get_post 读全文（付费文章只有试读段）。',
            ]);

        case 'ping':
            return ecod_mcp_ok($id, new \stdClass());

        case 'tools/list':
            return ecod_mcp_ok($id, ['tools' => ecod_mcp_tools()]);

        case 'tools/call':
            $params = is_array($req['params'] ?? null) ? $req['params'] : [];
            $name = (string) ($params['name'] ?? '');
            $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
            if ($name === '') {
                return ecod_mcp_err($id, -32602, '缺少工具名');
            }
            return ecod_mcp_ok($id, ecod_mcp_call($name, $args));

        case 'resources/list':
            /* 明确回空：客户端据此不再询问资源 */
            return ecod_mcp_ok($id, ['resources' => []]);

        case 'prompts/list':
            return ecod_mcp_ok($id, ['prompts' => []]);
    }

    return ecod_mcp_err($id, -32601, 'Method not found: ' . $method);
}

/**
 * 入口：由 api.php?action=mcp 调用
 *
 * 注意这里不校验同源（客户端是桌面 App / CLI），改为 Token 鉴权；
 * 同样不依赖登录态，所有数据都按「游客可见」口径取。
 */
function ecod_mcp_serve(): void
{
    if (!ecod_mcp_enabled()) {
        ecod_mcp_emit(ecod_mcp_err(null, -32600, 'MCP 未开启'), 404);
    }
    if (!ecod_mcp_auth_ok()) {
        header('WWW-Authenticate: Bearer');
        ecod_mcp_emit(ecod_mcp_err(null, -32001, 'Token 无效'), 401);
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        /* 有人用浏览器打开时给一句人话，而不是空 405 */
        ecod_mcp_emit([
            'jsonrpc' => '2.0',
            'result'  => [
                'service' => 'ecod-blog MCP',
                'usage'   => 'POST JSON-RPC 2.0 到本地址（initialize / tools/list / tools/call）',
                'tools'   => array_column(ecod_mcp_tools(), 'name'),
            ],
        ]);
    }
    if ($method !== 'POST') {
        header('Allow: POST');
        ecod_mcp_emit(ecod_mcp_err(null, -32600, 'Only POST is supported'), 405);
    }

    $raw = (string) file_get_contents('php://input');
    if ($raw === '' || strlen($raw) > 262144) {
        ecod_mcp_emit(ecod_mcp_err(null, -32700, 'Parse error'), 400);
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        ecod_mcp_emit(ecod_mcp_err(null, -32700, 'Parse error'), 400);
    }

    /* 批量：逐个处理，全是通知时按 202 回空 */
    $isList = function_exists('array_is_list')
        ? array_is_list($payload)
        : (array_keys($payload) === range(0, count($payload) - 1));
    if ($isList) {
        $out = [];
        foreach ($payload as $one) {
            if (!is_array($one)) {
                $out[] = ecod_mcp_err(null, -32600, 'Invalid Request');
                continue;
            }
            $res = ecod_mcp_dispatch($one);
            if (!empty($res['__notify'])) {
                continue;
            }
            $out[] = $res;
        }
        if (!$out) {
            ecod_mcp_status(202);
            exit;
        }
        ecod_mcp_emit($out);
    }

    $res = ecod_mcp_dispatch($payload);
    if (!empty($res['__notify'])) {
        ecod_mcp_status(202);
        exit;
    }

    ecod_mcp_emit($res);
}
