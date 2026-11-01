<?php
/**
 * 自定义文章查询组件（支持热门排序 / 指定分类 / 指定 cid）
 *
 * 用法：
 *   $this->widget('EcodPostQuery', 'pageSize=4&order=hot')->to($q);
 *   $this->widget('EcodPostQuery', 'pageSize=3&cids=1,2,3')->to($q);
 *   $this->widget('EcodPostQuery', 'pageSize=3&category=2')->to($q);
 *   $this->widget('EcodPostQuery', 'pageSize=3&related=25')->to($q);  // 同标签优先的相关文章
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class EcodPostQuery extends \Widget\Base\Contents
{
    /**
     * 组装主查询
     *
     * 参数见文件头用法示例；related 模式下行的顺序就是相关度序，
     * push 时不可打乱（Widget 栈顺序即输出顺序）。
     */
    public function execute()
    {
        $this->parameter->setDefault([
            'pageSize' => 6,
            'order'    => 'recent',
            'category' => 0,
            'cids'     => '',
            'exclude'  => 0,
            'related'  => 0,
            'airelated'=> 0,
            'days'     => 0,
            'offset'   => 0,
            'noteonly' => 0,
            'tags'     => '',
        ]);

        /* AI 相关（F13）：关键词打分序，行本身已排好序 */
        if ((int) $this->parameter->airelated > 0) {
            $aiRows = ecod_ai_related_rows(
                (int) $this->parameter->airelated,
                (int) $this->parameter->pageSize
            );
            foreach ($aiRows as $row) {
                $this->push($row);
            }
            return;
        }

        /* 相关文章：相关度由 core/content/related.php 计算，行已按相关度排好序，
           这里按顺序 push 就能保持顺序（Widget 栈的顺序即 push 顺序） */
        if ((int) $this->parameter->related > 0) {
            $relatedRows = ecod_related_rows(
                (int) $this->parameter->related,
                (int) $this->parameter->pageSize,
                $this->db
            );
            foreach ($relatedRows as $row) {
                $this->push($row);
            }
            return;
        }

        $fields = [
            'table.contents.cid',
            'table.contents.title',
            'table.contents.slug',
            'table.contents.created',
            'table.contents.modified',
            'table.contents.type',
            'table.contents.status',
            'table.contents.commentsNum',
            'table.contents.allowComment',
            'table.contents.allowPing',
            'table.contents.allowFeed',
            'table.contents.template',
            'table.contents.password',
            'table.contents.authorId',
            'table.contents.parent',
            'table.contents.text',
        ];
        /* 阅读量与正文同表，能取就一次取出，避免卡片再查一次 */
        if (ecod_views_available()) {
            $fields[] = 'table.contents.views';
        }

        $select = $this->select(...$fields)
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created < ?', $this->options->time)
            ->where('table.contents.type = ?', 'post');

        /* 指定 cid 集合（置顶 / 热门等） */
        $cids = trim((string) $this->parameter->cids);
        if ($cids !== '') {
            $list = array_filter(array_map('intval', explode(',', $cids)));
            if ($list) {
                $select->where('table.contents.cid IN (' . implode(',', $list) . ')');
            }
        }

        /* 指定分类（先取 cid 列表，避免子查询被构造器转义） */
        if ((int) $this->parameter->category > 0) {
            $catRows = $this->db->fetchAll(
                $this->db->select('cid')->from('table.relationships')
                    ->where('mid = ?', (int) $this->parameter->category)
            );
            $catCids = array_map('intval', array_filter(array_column($catRows, 'cid')));
            if (!$catCids) {
                return; // 该分类无文章
            }
            $select->where('table.contents.cid IN (' . implode(',', $catCids) . ')');
        }

        /* 排除指定 cid（相关推荐用）；支持逗号分隔的多个 cid（正文 [posts exclude="1,2"]） */
        $excludeRaw = trim((string) $this->parameter->exclude);
        if ($excludeRaw !== '' && $excludeRaw !== '0') {
            $excludeList = array_values(array_filter(array_map('intval', explode(',', $excludeRaw))));
            if (count($excludeList) === 1) {
                $select->where('table.contents.cid <> ?', $excludeList[0]);
            } elseif ($excludeList) {
                $select->where('table.contents.cid NOT IN (' . implode(',', $excludeList) . ')');
            }
        }

        /* 只要图文笔记（「笔记精选」模块用）：字段表里 note=on 的 cid 集合。
           与前台 ecod_note_on() 同源判据，但不读主题总开关——模块本身就是
           「有笔记就展示」，总开关关着时列表里也不会出现笔记字段。
           集合可能很大，超长时退化为不筛（宁可多给几张默认卡，也不发超长 IN）。 */
        if (!empty($this->parameter->noteonly)) {
            $noteRows = $this->db->fetchAll(
                $this->db->select('cid')->from('table.fields')
                    ->where('name = ?', 'note')->where('str_value = ?', 'on')
            );
            $noteCids = array_map('intval', array_filter(array_column($noteRows, 'cid')));
            if (!$noteCids) {
                return;
            }
            if (count($noteCids) <= 2000) {
                $select->where('table.contents.cid IN (' . implode(',', $noteCids) . ')');
            }
        }

        /* 按标签筛（slug 或 mid，逗号/空格分隔）：两步查询，避免子查询被转义 */
        $tagRaw = trim((string) $this->parameter->tags);
        if ($tagRaw !== '') {
            $picks = array_values(array_filter(preg_split('/[\s,，]+/', $tagRaw) ?: []));
            $tagMids = [];
            foreach ($picks as $one) {
                /* Db 没有 quote()：一行一查（模块参数通常 1–3 个标签），
                   数字当 mid、其余当 slug，两种都能用 */
                $oneQuery = $this->db->select('mid')->from('table.metas')->where('type = ?', 'tag');
                $oneQuery = preg_match('/^\d+$/', $one)
                    ? $oneQuery->where('mid = ?', (int) $one)
                    : $oneQuery->where('slug = ?', $one);
                foreach ($this->db->fetchAll($oneQuery) as $row) {
                    $tagMids[] = (int) $row['mid'];
                }
            }
            $tagMids = array_values(array_unique(array_filter($tagMids)));
            if (!$tagMids) {
                return; // 标签不存在，别静默返回全站
            }
            $relRows = $this->db->fetchAll(
                $this->db->select('cid')->from('table.relationships')
                    ->where('mid IN (' . implode(',', $tagMids) . ')')
            );
            $tagCids = array_values(array_unique(array_map('intval', array_filter(array_column($relRows, 'cid')))));
            if (!$tagCids) {
                return;
            }
            $select->where('table.contents.cid IN (' . implode(',', $tagCids) . ')');
        }

        /* 时间窗口：只统计最近 N 天（榜单日/周/月用），0 表示不限 */
        if ((int) $this->parameter->days > 0) {
            $select->where('table.contents.created >= ?', $this->options->time - (int) $this->parameter->days * 86400);
        }

        /* 排序：评论相关的一律按「已通过」实算（commentsNum 含待审且有滞后），
           挂一次按 cid 聚合的派生表，排序值与卡片上显示的评论数完全同源 */
        if ($this->parameter->order === 'views' && ecod_views_available()) {
            $select->order('table.contents.views', \Typecho\Db::SORT_DESC)
                ->order('table.contents.created', \Typecho\Db::SORT_DESC);
        } elseif ($this->parameter->order === 'weighted') {
            /* 加权热度（设计稿 guaqi-reference §F3）：views + 评论数×20；
               阅读量列不可用时退化为评论数排序，保证有结果 */
            $cmtCol = function_exists('ecod_comment_order_join') ? ecod_comment_order_join($select) : '';
            $cmtExpr = $cmtCol !== '' ? 'COALESCE(' . $cmtCol . ', 0)' : 'table.contents.commentsNum';
            if (ecod_views_available()) {
                $select->order('(table.contents.views + ' . $cmtExpr . ' * 20)', \Typecho\Db::SORT_DESC)
                    ->order('table.contents.created', \Typecho\Db::SORT_DESC);
            } else {
                $select->order($cmtExpr, \Typecho\Db::SORT_DESC)
                    ->order('table.contents.created', \Typecho\Db::SORT_DESC);
            }
        } elseif ($this->parameter->order === 'hot') {
            $cmtCol = function_exists('ecod_comment_order_join') ? ecod_comment_order_join($select) : '';
            $select->order($cmtCol !== '' ? $cmtCol : 'table.contents.commentsNum', \Typecho\Db::SORT_DESC)
                ->order('table.contents.created', \Typecho\Db::SORT_DESC);
        } else {
            $select->order('table.contents.created', \Typecho\Db::SORT_DESC);
        }

        /* 随机：取最近 200 篇做池子，PHP 侧打乱，避免依赖各数据库的 RAND() 方言 */
        if ($this->parameter->order === 'rand') {
            $select->limit(200);
            $rows = $this->db->fetchAll($select);
            shuffle($rows);
            foreach (array_slice($rows, 0, max(1, (int) $this->parameter->pageSize)) as $row) {
                $this->push($row);
            }
            return;
        }

        $select->limit((int) $this->parameter->pageSize);

        /* 偏移：专题详情等「固定 cids 集合 + 分页」的场景用，其余调用不受影响 */
        if ((int) $this->parameter->offset > 0) {
            $select->offset((int) $this->parameter->offset);
        }

        $this->db->fetchAll($select, [$this, 'push']);
    }
}
