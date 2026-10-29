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
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
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
            'days'     => 0,
            'offset'   => 0,
        ]);

        /* 相关文章：相关度由 core/related.php 计算，行已按相关度排好序，
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

        /* 排除指定 cid（相关推荐用） */
        if ((int) $this->parameter->exclude > 0) {
            $select->where('table.contents.cid <> ?', (int) $this->parameter->exclude);
        }

        /* 时间窗口：只统计最近 N 天（榜单日/周/月用），0 表示不限 */
        if ((int) $this->parameter->days > 0) {
            $select->where('table.contents.created >= ?', $this->options->time - (int) $this->parameter->days * 86400);
        }

        /* 排序 */
        if ($this->parameter->order === 'views' && ecod_views_available()) {
            $select->order('table.contents.views', \Typecho\Db::SORT_DESC)
                ->order('table.contents.created', \Typecho\Db::SORT_DESC);
        } elseif ($this->parameter->order === 'hot') {
            $select->order('table.contents.commentsNum', \Typecho\Db::SORT_DESC)
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
