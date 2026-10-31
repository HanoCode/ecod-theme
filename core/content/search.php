<?php
/**
 * Ecod Theme · 搜索增强
 *
 * - 分词：把整串关键词按空白/标点拆成词元，去掉停用词
 * - 命中片段：正文里取关键词上下文片段并高亮，替代固定开头的摘要
 * - 空结果建议：逐词统计命中数并给出可点击的二次搜索，附最新文章兜底
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!function_exists('ecod_search_terms')) {
    /**
     * 关键词分词
     *
     * @param string $kw 原始关键词
     * @return string[] 去重后的词元（最多 6 个）
     */
    function ecod_search_terms(string $kw): array
    {
        $kw = trim($kw);
        if ($kw === '') {
            return [];
        }

        $stop = [
            '的' => 1, '了' => 1, '是' => 1, '在' => 1, '和' => 1, '与' => 1, '及' => 1,
            '或' => 1, '一个' => 1, '这个' => 1, '那个' => 1, '我们' => 1, '怎么' => 1,
            '如何' => 1, '什么' => 1, 'the' => 1, 'a' => 1, 'an' => 1, 'of' => 1,
            'to' => 1, 'in' => 1, 'on' => 1, 'and' => 1, 'or' => 1, 'is' => 1,
            'are' => 1, 'for' => 1, 'with' => 1, 'how' => 1, 'what' => 1,
        ];

        $raw = preg_split('/[\s,，、;；|+]+/u', $kw) ?: [];
        $terms = [];

        foreach ($raw as $term) {
            $term = trim($term);
            if ($term === '' || mb_strlen($term, 'UTF-8') < 2) {
                continue;
            }
            $lower = function_exists('mb_strtolower') ? mb_strtolower($term, 'UTF-8') : strtolower($term);
            if (isset($stop[$lower])) {
                continue;
            }
            if (!in_array($term, $terms, true)) {
                $terms[] = $term;
            }
            if (count($terms) >= 6) {
                break;
            }
        }

        /* 没拆出词元时按「字母数字段 / 中文段」切分：
           Typecho 核心会把搜索词转成 slug（空格在 URL 链路上必然丢失），
           到达搜索页的往往是「PHP主题」「测试卡片」这类粘连串，
           按脚本边界切开才能给出有意义的高亮与二次搜索。 */
        if (count($terms) === 1) {
            $blob = $terms[0];
            if (preg_match_all('/[A-Za-z0-9]{2,}|[\x{4e00}-\x{9fff}]{2,}|\d/u', $blob, $runs)) {
                $runs = array_values(array_unique($runs[0]));
                if (count($runs) > 1) {
                    $terms = array_slice($runs, 0, 6);
                }
            }
        }

        /* 仍然没有词元时退回原串，保证高亮和建议可用 */
        if (!$terms) {
            $terms = [$kw];
        }

        return $terms;
    }
}

if (!function_exists('ecod_search_snippet')) {
    /**
     * 命中片段：取关键词上下文并高亮
     *
     * 与固定开头的第一段摘要不同，这里优先展示「关键词附近」的正文，
     * 没有命中时退回普通摘要。返回已转义 HTML，调用方直接输出。
     *
     * @param \Widget\Base\Contents $archive
     * @param string[]              $terms  词元
     * @param int                   $window 关键词两侧保留的字符数
     * @return string
     */
    function ecod_search_snippet($archive, array $terms, int $window = 80): string
    {
        if (!empty($archive->hidden)) {
            return '此内容受密码保护，输入密码后可查看。';
        }

        $text = strip_tags((string) $archive->content);
        if (function_exists('ecod_shortcode_strip')) {
            $text = ecod_shortcode_strip($text);
        }
        $text = preg_replace('/\s+/u', ' ', trim((string) $text)) ?? $text;

        if (!$terms) {
            return htmlspecialchars(ecod_excerpt($archive), ENT_QUOTES, 'UTF-8');
        }

        /* 找最早的命中位置 */
        $pos = false;
        foreach ($terms as $term) {
            $found = mb_stripos($text, $term, 0, 'UTF-8');
            if ($found !== false && ($pos === false || $found < $pos)) {
                $pos = $found;
            }
        }

        if ($pos === false) {
            return ecod_highlight(ecod_excerpt($archive, $window * 2), implode(' ', $terms));
        }

        $start = max(0, $pos - $window);
        $end = min(mb_strlen($text, 'UTF-8'), $pos + mb_strlen($terms[0], 'UTF-8') + $window);
        $snippet = mb_substr($text, $start, $end - $start, 'UTF-8');

        return ($start > 0 ? '…' : '') . ecod_search_marks($snippet, $terms) . ($end < mb_strlen($text, 'UTF-8') ? '…' : '');
    }
}

if (!function_exists('ecod_search_marks')) {
    /**
     * 多词高亮：单遍正则，长词优先，避免短词吃掉长词的一部分
     *
     * @param string   $text  纯文本
     * @param string[] $terms 词元
     * @return string 已转义 HTML
     */
    function ecod_search_marks(string $text, array $terms): string
    {
        $terms = array_values(array_filter(array_map('trim', $terms)));
        if (!$terms) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        usort($terms, static function ($a, $b) {
            return mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
        });

        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $pattern = implode('|', array_map(static function ($term) {
            return preg_quote(htmlspecialchars($term, ENT_QUOTES, 'UTF-8'), '/');
        }, $terms));

        return preg_replace('/(' . $pattern . ')/iu', '<mark class="ecod_kw">$1</mark>', $escaped) ?? $escaped;
    }
}

if (!function_exists('ecod_search_counts')) {
    /**
     * 逐词统计命中数（标题或正文，发布文章）
     *
     * @param string[] $terms
     * @return array<string, int> 词元 => 命中篇数
     */
    function ecod_search_counts(array $terms): array
    {
        if (!$terms) {
            return [];
        }

        $count = static function (string $term): int {
            /* LIKE '%kw%' 前导通配必然全表扫描，空结果页逐词 COUNT 可达 10 次；
               建议词频只做提示用，缓存 10 分钟可接受 */
            if (function_exists('ecod_cache_remember')) {
                return (int) ecod_cache_remember('search_count_' . md5($term), 600, static function () use ($term) {
                    return ecod_search_count_query($term);
                });
            }
            return ecod_search_count_query($term);
        };

        try {
            $out = [];
            foreach ($terms as $term) {
                $out[$term] = $count($term);
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    function ecod_search_count_query(string $term): int
    {
        try {
            $db = \Typecho\Db::get();
            $prefix = $db->getPrefix();
            $now = time();
            $like = $db->getAdapter()->quoteValue('%' . addcslashes($term, '\\%_') . '%');
            $row = $db->fetchRow(
                "SELECT COUNT(*) AS num FROM {$prefix}contents
                  WHERE type = 'post' AND status = 'publish' AND created < {$now}
                    AND (title LIKE {$like} OR text LIKE {$like})"
            );
            return $row ? (int) $row['num'] : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('ecod_search_empty')) {
    /**
     * 空结果建议：逐词二次搜索 + 最新文章兜底
     *
     * @param \Widget\Archive $archive
     */
    function ecod_search_empty($archive): void
    {
        $kw = trim((string) ($GLOBALS['ecodSearchKw'] ?? ''));
        $terms = ecod_search_terms($kw);
        $counts = ecod_search_counts($terms);

        /* 全部为 0 且是较长的纯中文串：补中文二元词，给用户可点的出口 */
        $extra = [];
        if ($terms && array_sum($counts) === 0 && preg_match('/^[\x{4e00}-\x{9fff}]+$/u', $terms[0])) {
            $len = mb_strlen($terms[0], 'UTF-8');
            if ($len >= 4) {
                for ($i = 0; $i + 2 <= $len && count($extra) < 4; $i++) {
                    $gram = mb_substr($terms[0], $i, 2, 'UTF-8');
                    if (!in_array($gram, $terms, true)) {
                        $extra[] = $gram;
                    }
                }
            }
        }
        if ($extra) {
            $counts += ecod_search_counts($extra);
            $counts = array_filter($counts);
        }
        ?>
        <div class="ecod_sugg">
            <?php ecod_empty('search', '没有找到「' . htmlspecialchars($kw !== '' ? $kw : $archive->request->get('keywords', ''), ENT_QUOTES, 'UTF-8') . '」相关的文章', '试试更短的关键词，或者从下面挑一个方向'); ?>

            <?php
            $hits = [];
            foreach ($counts as $term => $num) {
                if ($num > 0) {
                    $hits[] = ['term' => $term, 'num' => $num];
                }
            }
            if ($hits):
                usort($hits, static function ($a, $b) {
                    return $b['num'] <=> $a['num'];
                });
            ?>
                <div class="ecod_sugg__row">
                    <span class="ecod_sugg__label"><?php echo ecod_icon('bulb', 13); ?>这些词有结果：</span>
                    <?php foreach (array_slice($hits, 0, 5) as $hit): ?>
                        <a class="ecod_sugg__chip" href="<?php echo \Typecho\Router::url('search', ['keywords' => $hit['term']], \Widget\Options::alloc()->index); ?>">
                            <?php echo htmlspecialchars($hit['term'], ENT_QUOTES, 'UTF-8'); ?><i><?php echo $hit['num']; ?> 篇</i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php
            $archive->widget('EcodPostQuery@sugg', 'pageSize=4')->to($ecodSugg);
            if ($ecodSugg->have()):
            ?>
                <div class="ecod_sugg__row">
                    <span class="ecod_sugg__label"><?php echo ecod_icon('zap', 13); ?>不妨看看最新的：</span>
                    <div class="ecod_sugg__list">
                        <?php while ($ecodSugg->next()): ?>
                            <a class="ecod_sugg__item" href="<?php $ecodSugg->permalink(); ?>">
                                <b><?php $ecodSugg->title(); ?></b>
                                <time datetime="<?php $ecodSugg->date('Y-m-d'); ?>"><?php $ecodSugg->date('m-d'); ?></time>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('ecod_search_hotwords')) {
    /**
     * 搜索面板热词
     *
     * 真源是设置项 ESearchHot（逗号 / 顿号分隔）；解析后最多取 8 个。
     * 为空时前端自行回退到热门标签，这里只负责把配置解析成数组。
     *
     * @return string[] 热词列表
     */
    function ecod_search_hotwords(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $raw = trim((string) ecod_option('ESearchHot', ''));
        $cache = [];
        if ($raw !== '') {
            /* 中西文分隔符都认：，、；| 以及 ASCII 逗号分号 */
            $raw = str_replace(['，', '、', '；'], ',', $raw);
            $raw = str_replace([';', '|'], ',', $raw);
            foreach (explode(',', $raw) as $word) {
                $word = trim($word);
                if ($word === '' || in_array($word, $cache, true)) {
                    continue;
                }
                $cache[] = mb_substr($word, 0, 20, 'UTF-8');
                if (count($cache) >= 8) {
                    break;
                }
            }
        }

        return $cache;
    }
}

if (!function_exists('ecod_search_featured')) {
    /**
     * 搜索面板空态推荐文章
     *
     * 按设置项 ESearchPosts 的维度取前 N 篇，只查 cid / title / created，
     * 链接由前端用 postUrl 模板按 cid 拼（不依赖路由里有没有 slug 段）。
     * 聚合查询照例缓存 10 分钟：面板是高频入口，不值得每次点开都扫表。
     *
     * @param int    $limit 条数
     * @param string $order hot / likes / comments / views
     * @return array<int, array{cid:int,title:string,date:string}>
     */
    function ecod_search_featured(int $limit = 8, string $order = 'hot'): array
    {
        $limit = max(1, min(12, $limit));
        if (!in_array($order, ['hot', 'likes', 'comments', 'views'], true)) {
            return [];
        }

        $run = static function () use ($limit, $order): array {
            try {
                $db = \Typecho\Db::get();
                $prefix = $db->getPrefix();
                $now = time();
                $where = "c.type = 'post' AND c.status = 'publish' AND c.created < {$now}";
                $views = ecod_views_available() ? 'c.views' : '0';
                /* 评论数按「已通过」实算：commentsNum 含待审且有滞后，
                   会和卡片上显示的条数对不上 */
                $cmtJoin = "LEFT JOIN (SELECT cid, COUNT(*) AS cmt FROM {$prefix}comments"
                    . " WHERE status = 'approved' GROUP BY cid) ecod_cmt ON ecod_cmt.cid = c.cid";
                $cmtNum = 'COALESCE(ecod_cmt.cmt, 0)';

                if ($order === 'likes') {
                    $sql = "SELECT c.cid, c.title, c.created, COUNT(l.cid) AS num
                              FROM {$prefix}contents c
                        INNER JOIN {$prefix}ecod_likes l ON l.cid = c.cid
                             WHERE {$where}
                          GROUP BY c.cid, c.title, c.created
                          ORDER BY num DESC, c.created DESC
                             LIMIT {$limit}";
                } elseif ($order === 'comments') {
                    $sql = "SELECT c.cid, c.title, c.created
                              FROM {$prefix}contents c
                              {$cmtJoin}
                             WHERE {$where}
                          ORDER BY {$cmtNum} DESC, c.created DESC
                             LIMIT {$limit}";
                } elseif ($order === 'views') {
                    if (!ecod_views_available()) {
                        return [];
                    }
                    $sql = "SELECT c.cid, c.title, c.created
                              FROM {$prefix}contents c
                             WHERE {$where}
                          ORDER BY c.views DESC, c.created DESC
                             LIMIT {$limit}";
                } else {
                    /* 热度：评论加权 8，与列表排序里「最多评论」的直观程度保持一致 */
                    $sql = "SELECT c.cid, c.title, c.created
                              FROM {$prefix}contents c
                              {$cmtJoin}
                             WHERE {$where}
                          ORDER BY ({$cmtNum} * 8 + {$views}) DESC, c.created DESC
                             LIMIT {$limit}";
                }

                $rows = $db->fetchAll($sql);
                $out = [];
                foreach ((array) $rows as $row) {
                    $title = trim(strip_tags((string) ($row['title'] ?? '')));
                    if ($title === '') {
                        continue;
                    }
                    $out[] = [
                        'cid'   => (int) ($row['cid'] ?? 0),
                        'title' => mb_substr($title, 0, 60, 'UTF-8'),
                        'date'  => date('m-d', (int) ($row['created'] ?? 0)),
                    ];
                }
                return $out;
            } catch (\Throwable $e) {
                return [];
            }
        };

        if (function_exists('ecod_cache_remember')) {
            return (array) ecod_cache_remember('spanel_posts_' . $order . '_' . $limit, 600, $run);
        }
        return $run();
    }
}
