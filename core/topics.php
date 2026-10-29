<?php
/**
 * Ecod Theme · 专题
 *
 * 专题是「编辑挑出来的一组文章」，和分类（文章属于哪里）不是一层东西。
 * Typecho 没有自定义分类法，因此不进 metas 表，沿用主题自己的 JSON 选项：
 *
 *   ETopics = {"items":[{id, name, slug, desc, cover, cids:[], on}]}
 *
 * 成员来源两路合并（去重）：
 *   1. 文章自定义字段 topic = slug（写作页下拉选择）
 *   2. 专题自身的 cids（后台构建器里手挑，不改文章也能加稿）
 *
 * 顺序一律 created DESC：专题是策展，读者要的是叙事顺序，不是热度榜。
 *
 * @package Ecod Theme
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/** 专题存储的 option 名 */
define('ECOD_TOPICS_OPTION', 'ETopics');

/** 最多几个专题 */
define('ECOD_TOPICS_MAX', 30);

/** 单个专题最多存几个手动 cid */
define('ECOD_TOPIC_CIDS_MAX', 200);

/**
 * 专题别名清洗
 *
 * 只留小写字母、数字与连字符；中文名不会自动转拼音，
 * 由后台手填（留空时按序号生成 topic-1 之类，保证地址永远可用）。
 */
function ecod_topic_slug(string $raw): string
{
    $slug = strtolower(trim($raw));
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?: '';
    $slug = trim((string) preg_replace('/-{2,}/', '-', $slug), '-');
    return substr($slug, 0, 40);
}

/**
 * 归一化单个专题
 *
 * @param mixed $row 原始数组
 * @return array<string, mixed>|null
 */
function ecod_topic_norm($row): ?array
{
    if (!is_array($row)) {
        return null;
    }

    $name = trim((string) ($row['name'] ?? ''));
    if ($name === '') {
        return null;
    }

    $cids = [];
    $raw = $row['cids'] ?? [];
    if (is_string($raw)) {
        $raw = preg_split('/[,\s]+/', $raw) ?: [];
    }
    foreach ((array) $raw as $cid) {
        $cid = (int) $cid;
        if ($cid > 0 && !in_array($cid, $cids, true)) {
            $cids[] = $cid;
            if (count($cids) >= ECOD_TOPIC_CIDS_MAX) {
                break;
            }
        }
    }

    return [
        'id'    => trim((string) ($row['id'] ?? '')),
        'name'  => mb_substr($name, 0, 40, 'UTF-8'),
        'slug'  => ecod_topic_slug((string) ($row['slug'] ?? '')),
        'desc'  => mb_substr(trim((string) ($row['desc'] ?? '')), 0, 200, 'UTF-8'),
        'cover' => trim((string) ($row['cover'] ?? '')),
        'cids'  => $cids,
        'on'    => !isset($row['on']) || !empty($row['on']),
    ];
}

/**
 * 全部专题（按后台顺序）
 *
 * slug 去重：重复的按 `-2`、`-3` 顺延，保证前台地址唯一；
 * 清洗后为空的按序号补 `topic-N`，避免存了数据却点不开。
 *
 * @param bool $onlyOn 只要启用的
 * @return array<int, array<string, mixed>>
 */
function ecod_topics(bool $onlyOn = true): array
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        $raw = trim((string) ecod_option(ECOD_TOPICS_OPTION, ''));
        if ($raw !== '') {
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data['items']) && is_array($data['items'])) {
                $data = $data['items'];
            }
            foreach ((array) $data as $row) {
                if (count($cache) >= ECOD_TOPICS_MAX) {
                    break;
                }
                $item = ecod_topic_norm($row);
                if ($item === null) {
                    continue;
                }
                if ($item['id'] === '') {
                    $item['id'] = 't' . (count($cache) + 1);
                }
                if ($item['slug'] === '') {
                    $item['slug'] = 'topic-' . (count($cache) + 1);
                }
                $base = $item['slug'];
                $i = 2;
                $taken = array_column($cache, 'slug');
                while (in_array($item['slug'], $taken, true)) {
                    $item['slug'] = $base . '-' . $i;
                    $i++;
                }
                $cache[] = $item;
            }
        }
    }

    if (!$onlyOn) {
        return $cache;
    }

    return array_values(array_filter($cache, static function ($t) {
        return !empty($t['on']);
    }));
}

/**
 * 按 slug 取一个启用的专题
 *
 * @return array<string, mixed>|null
 */
function ecod_topic_find(string $slug): ?array
{
    $slug = ecod_topic_slug($slug);
    if ($slug === '') {
        return null;
    }
    foreach (ecod_topics() as $topic) {
        if ($topic['slug'] === $slug) {
            return $topic;
        }
    }
    return null;
}

/**
 * DB 句柄（失败返回 null，调用方按空数据处理）
 *
 * @return \Typecho\Db|null
 */
function ecod_topic_db(): ?\Typecho\Db
{
    try {
        return \Typecho\Db::get();
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * 全站专题索引：slug => [cids, count, latest, latest_title]
 *
 * 两次查询搞定（字段命中一次、手动 cid 的元信息一次），
 * 墙页 / 详情页 / 分类页 chips 共用，同一请求内只算一次。
 *
 * @return array<string, array<string, mixed>>
 */
function ecod_topic_index(): array
{
    static $index = null;
    if ($index !== null) {
        return $index;
    }

    $topics = ecod_topics();
    $index = [];
    foreach ($topics as $topic) {
        $index[$topic['slug']] = [
            'cids'         => [],
            'count'        => 0,
            'latest'       => 0,
            'latest_cid'   => 0,
            'latest_title' => '',
            'manual'       => [],
        ];
    }
    if (!$topics) {
        return $index;
    }

    $db = ecod_topic_db();
    if (!$db) {
        return $index;
    }

    /* 字段命中的文章：一次 JOIN 取 slug / cid / created / title */
    $fieldRows = [];
    try {
        $now = (int) (\Widget\Options::alloc()->time ?? time());
        $fieldRows = $db->fetchAll(
            $db->select(
                'table.fields.str_value',
                'table.fields.int_value',
                'table.contents.cid',
                'table.contents.created',
                'table.contents.title'
            )
                ->from('table.fields')
                ->join('table.contents', 'table.contents.cid = table.fields.cid')
                ->where('table.fields.name = ?', 'topic')
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.created < ?', $now)
                ->order('table.contents.created', \Typecho\Db::SORT_DESC)
        );
    } catch (\Throwable $e) {
        $fieldRows = [];
    }

    /* 字段里可能写了多个 slug（历史数据），逗号切分后逐个计入 */
    $meta = [];
    foreach ($fieldRows as $row) {
        $cid = (int) $row['cid'];
        $meta[$cid] = [
            'created' => (int) $row['created'],
            'title'   => (string) $row['title'],
        ];
        /* 下拉字段存 str_value，手填数字时落在 int_value，两列都认 */
        $raw = trim((string) $row['str_value']);
        if ($raw === '') {
            $raw = (string) (int) $row['int_value'];
        }
        foreach (preg_split('/[,\s]+/', strtolower($raw)) ?: [] as $slug) {
            $slug = ecod_topic_slug($slug);
            if ($slug !== '' && isset($index[$slug]) && !in_array($cid, $index[$slug]['cids'], true)) {
                $index[$slug]['cids'][] = $cid;
            }
        }
    }

    /* 手动 cid 的元信息：一条 IN 查询补齐（可能不在字段命中集合里） */
    $manualAll = [];
    foreach ($topics as $topic) {
        foreach ($topic['cids'] as $cid) {
            $manualAll[$cid] = true;
        }
    }
    $missing = array_diff(array_keys($manualAll), array_keys($meta));
    if ($missing) {
        try {
            $rows = $db->fetchAll(
                $db->select('cid', 'created', 'title')
                    ->from('table.contents')
                    ->where('cid IN (' . implode(',', array_map('intval', $missing)) . ')')
                    ->where('type = ?', 'post')
                    ->where('status = ?', 'publish')
            );
            foreach ($rows as $row) {
                $meta[(int) $row['cid']] = [
                    'created' => (int) $row['created'],
                    'title'   => (string) $row['title'],
                ];
            }
        } catch (\Throwable $e) {
        }
    }

    foreach ($topics as $topic) {
        $slug = $topic['slug'];
        $cids = $index[$slug]['cids'];
        $manual = [];
        foreach ($topic['cids'] as $cid) {
            /* 手动挑的稿必须仍然存在且已发布，被删的文章不留在专题里 */
            if (!isset($meta[$cid])) {
                continue;
            }
            $manual[] = $cid;
            if (!in_array($cid, $cids, true)) {
                $cids[] = $cid;
            }
        }
        $index[$slug]['cids'] = $cids;
        $index[$slug]['manual'] = $manual;
        $index[$slug]['count'] = count($cids);

        $latest = 0;
        $latestCid = 0;
        $latestTitle = '';
        foreach ($cids as $cid) {
            if (($meta[$cid]['created'] ?? 0) > $latest) {
                $latest = (int) $meta[$cid]['created'];
                $latestCid = (int) $cid;
                $latestTitle = (string) ($meta[$cid]['title'] ?? '');
            }
        }
        $index[$slug]['latest'] = $latest;
        $index[$slug]['latest_cid'] = $latestCid;
        $index[$slug]['latest_title'] = $latestTitle;
    }

    return $index;
}

/**
 * 某个专题的文章 cid 列表
 *
 * @return int[]
 */
function ecod_topic_cids(string $slug): array
{
    $slug = ecod_topic_slug($slug);
    $index = ecod_topic_index();
    return $index[$slug]['cids'] ?? [];
}

/**
 * 文章所属专题的 slug（没有则空串）
 *
 * 列表页由 ecod_cards_preload 批量预热，单篇文章页按需查一次。
 */
function ecod_topic_of_cid(int $cid): string
{
    if ($cid <= 0) {
        return '';
    }

    $map = &ecod_topic_preload_map();
    if (isset($map[$cid])) {
        return $map[$cid];
    }

    $map[$cid] = '';
    $db = ecod_topic_db();
    if (!$db) {
        return '';
    }

    try {
        $row = $db->fetchRow(
            $db->select('table.fields.str_value', 'table.fields.int_value')
                ->from('table.fields')
                ->where('cid = ?', $cid)
                ->where('name = ?', 'topic')
                ->limit(1)
        );
        if ($row) {
            $raw = trim((string) $row['str_value']);
            if ($raw === '') {
                $raw = (string) (int) $row['int_value'];
            }
            foreach (preg_split('/[,\s]+/', strtolower($raw)) ?: [] as $slug) {
                $slug = ecod_topic_slug($slug);
                if ($slug !== '' && ecod_topic_find($slug) !== null) {
                    $map[$cid] = $slug;
                    break;
                }
            }
        }
    } catch (\Throwable $e) {
    }

    /* 字段没设、但被手动挑进某个专题：同样算归属 */
    if ($map[$cid] === '') {
        foreach (ecod_topics() as $topic) {
            if (in_array($cid, $topic['cids'], true)) {
                $map[$cid] = $topic['slug'];
                break;
            }
        }
    }

    return $map[$cid];
}

/**
 * 专题映射的请求级缓存（引用返回，便于就地写入）
 *
 * @return array<int, string>
 */
function &ecod_topic_preload_map(): array
{
    if (!isset($GLOBALS['ecodTopicMap']) || !is_array($GLOBALS['ecodTopicMap'])) {
        $GLOBALS['ecodTopicMap'] = [];
    }
    return $GLOBALS['ecodTopicMap'];
}

/**
 * 列表渲染前批量预取专题归属（一条 IN 查询）
 *
 * @param int[] $cids
 */
function ecod_topic_preload(array $cids): void
{
    $cids = array_values(array_unique(array_filter(array_map('intval', $cids))));
    if (!$cids) {
        return;
    }

    $map = &ecod_topic_preload_map();
    $todo = array_values(array_filter($cids, static function ($cid) use (&$map) {
        return !isset($map[$cid]);
    }));
    if (!$todo) {
        return;
    }

    foreach ($todo as $cid) {
        $map[$cid] = '';
    }

    $db = ecod_topic_db();
    if (!$db) {
        return;
    }

    try {
        $rows = $db->fetchAll(
            $db->select('table.fields.cid', 'table.fields.str_value', 'table.fields.int_value')
                ->from('table.fields')
                ->where('cid IN (' . implode(',', $todo) . ')')
                ->where('name = ?', 'topic')
        );
    } catch (\Throwable $e) {
        return;
    }

    $valid = array_column(ecod_topics(), 'slug');
    foreach ($rows as $row) {
        $raw = trim((string) $row['str_value']);
        if ($raw === '') {
            $raw = (string) (int) $row['int_value'];
        }
        foreach (preg_split('/[,\s]+/', strtolower($raw)) ?: [] as $slug) {
            $slug = ecod_topic_slug($slug);
            if ($slug !== '' && in_array($slug, $valid, true)) {
                $map[(int) $row['cid']] = $slug;
                break;
            }
        }
    }

    /* 手动挑进专题的文章也应显示角标：专题 JSON 已在内存，零额外查询 */
    foreach (ecod_topics() as $topic) {
        foreach ($topic['cids'] as $cid) {
            if ($cid > 0 && ($map[$cid] ?? '') === '') {
                $map[$cid] = $topic['slug'];
            }
        }
    }
}

/**
 * 卡片上的专题角标
 *
 * @param mixed $archive 归档对象
 * @return string HTML（未开启 / 无归属时为空串）
 */
function ecod_topic_chip($archive): string
{
    if (ecod_option('ETopicCardChip', 'on') === 'off') {
        return '';
    }

    $cid = (int) ($archive->cid ?? 0);
    $slug = ecod_topic_of_cid($cid);
    if ($slug === '') {
        return '';
    }

    $topic = ecod_topic_find($slug);
    if ($topic === null) {
        return '';
    }

    return '<a class="ecod_card__meta-topic" href="' . htmlspecialchars(ecod_topic_url($slug), ENT_QUOTES, 'UTF-8') . '">'
        . ecod_icon('folder', 12) . htmlspecialchars($topic['name'], ENT_QUOTES, 'UTF-8') . '</a>';
}

/**
 * 专题页所在独立页面
 *
 * 按模板反查（用户把 slug 从 topics 改成别的也不影响），
 * 找不到时退回 slug=topics 的约定地址。
 *
 * @return array{slug:string, title:string, url:string}
 */
function ecod_topics_page(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = ['slug' => 'topics', 'title' => '专题', 'url' => ''];
    $db = ecod_topic_db();
    if ($db) {
        try {
            $row = $db->fetchRow(
                $db->select('slug', 'title')->from('table.contents')
                    ->where('type = ?', 'page')
                    ->where('status = ?', 'publish')
                    ->where('template = ?', 'page-topics.php')
                    ->limit(1)
            );
            if ($row) {
                $cache['slug'] = (string) $row['slug'];
                $cache['title'] = (string) $row['title'];
            }
        } catch (\Throwable $e) {
        }
    }

    try {
        $prefix = (string) \Widget\Options::alloc()->index;
        $url = (string) \Typecho\Router::url('page', ['slug' => $cache['slug']], $prefix);
        /* 路由表缺失时 Router::url 返回 #：按页面默认地址模板兜底 */
        if ($url === '' || $url === '#') {
            $url = (string) \Typecho\Common::url($cache['slug'] . '.html', $prefix);
        }
        $cache['url'] = $url;
    } catch (\Throwable $e) {
        $cache['url'] = '';
    }

    return $cache;
}

/**
 * 专题地址（专题墙 + ?t=slug）
 */
function ecod_topic_url(string $slug): string
{
    $page = ecod_topics_page();
    $base = $page['url'] !== '' ? $page['url'] : '/';
    $sep = strpos($base, '?') === false ? '?' : '&';
    return $base . $sep . 't=' . rawurlencode(ecod_topic_slug($slug));
}

/**
 * 专题墙地址
 */
function ecod_topics_url(): string
{
    $page = ecod_topics_page();
    return $page['url'] !== '' ? $page['url'] : '/';
}

/**
 * 当前请求是不是「专题」。用于 <title> / canonical 与模板分支。
 *
 * @return array{topic:array<string,mixed>, page:array<string,string>}|null
 */
function ecod_topic_current(): ?array
{
    static $resolved = false;
    static $current = null;

    if ($resolved) {
        return $current;
    }
    $resolved = true;

    try {
        $request = \Typecho\Request::getInstance();
        $slug = ecod_topic_slug((string) $request->get('t', ''));
    } catch (\Throwable $e) {
        return null;
    }
    if ($slug === '') {
        return null;
    }

    /* 只有落在专题页模板上才算，避免 ?t= 被其他页面误用 */
    try {
        $archive = \Widget\Archive::alloc();
        if (!$archive->is('page')) {
            return null;
        }
        if ($archive->template !== 'page-topics.php') {
            return null;
        }
    } catch (\Throwable $e) {
        return null;
    }

    $topic = ecod_topic_find($slug);
    if ($topic === null) {
        return null;
    }

    $current = ['topic' => $topic, 'page' => ecod_topics_page()];
    return $current;
}

/**
 * 批量取专题封面：专题自带封面优先，否则用最新一篇文章的首图
 *
 * @param string[] $slugs
 * @return array<string, string>
 */
function ecod_topic_covers(array $slugs): array
{
    $out = [];
    $need = [];
    $index = ecod_topic_index();

    foreach ($slugs as $slug) {
        $topic = ecod_topic_find((string) $slug);
        if ($topic === null) {
            continue;
        }
        if ($topic['cover'] !== '') {
            $out[$topic['slug']] = $topic['cover'];
            continue;
        }
        /* 没有自带封面：用最新一篇的正文首图（墙页一次 IN 取回） */
        $out[$topic['slug']] = '';
        $latestCid = (int) ($index[$topic['slug']]['latest_cid'] ?? 0);
        if ($latestCid > 0) {
            $need[$latestCid] = $topic['slug'];
        }
    }

    if ($need) {
        $db = ecod_topic_db();
        if ($db) {
            $noImg = [];
            try {
                $rows = $db->fetchAll(
                    $db->select('cid', 'text')->from('table.contents')
                        ->where('cid IN (' . implode(',', array_map('intval', array_keys($need))) . ')')
                );
                foreach ($rows as $row) {
                    $img = '';
                    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', (string) $row['text'], $m)) {
                        $img = (string) $m[1];
                    }
                    if ($img !== '') {
                        $out[$need[(int) $row['cid']]] = $img;
                    } else {
                        $noImg[] = (int) $row['cid'];
                    }
                }
            } catch (\Throwable $e) {
            }

            /* 正文没图时退到「自定义缩略图」字段（很多站只填这一项） */
            if ($noImg) {
                try {
                    $rows = $db->fetchAll(
                        $db->select('cid', 'str_value')->from('table.fields')
                            ->where('name = ?', 'thumb')
                            ->where('cid IN (' . implode(',', $noImg) . ')')
                    );
                    foreach ($rows as $row) {
                        $lines = preg_split('/\r\n|\r|\n/', trim((string) $row['str_value'])) ?: [];
                        $first = trim((string) ($lines[0] ?? ''));
                        if ($first !== '') {
                            $out[$need[(int) $row['cid']]] = $first;
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
        }
    }

    return $out;
}
