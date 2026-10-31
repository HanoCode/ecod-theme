<?php
/**
 * 归档时间轴：按年 / 月折叠的全站文章时间轴
 *
 * @package custom
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 *
 * 在后台「页面 → 新建页面 → 自定义模板」里选择「归档时间轴」即可。
 * 一次 SQL 取全部已发布文章，按年 / 月分组折叠，不依赖 Widget 分页。
 * （说明：模板下拉只显示 @package 之前的一行，长说明请留在 @package 之后）
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');

$ecodDb = \Typecho\Db::get();
$ecodRows = [];
$ecodError = '';

try {
    $ecodRows = $ecodDb->fetchAll(
        $ecodDb->select('cid', 'slug', 'title', 'created', 'commentsNum', 'password')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('status = ?', 'publish')
            ->where('created < ?', time())
            ->order('created', \Typecho\Db::SORT_DESC)
            ->limit(2000)
    );
} catch (\Throwable $e) {
    $ecodError = '读取文章列表失败';
}

/* 年 → 月 → 文章 */
$ecodYears = [];
$ecodTotal = 0;
$ecodMinYear = 0;
$ecodMaxYear = 0;

foreach ($ecodRows as $ecodRow) {
    if (!empty($ecodRow['password'])) {
        continue;
    }

    $ts = (int) $ecodRow['created'];
    $year = (int) date('Y', $ts);
    $month = (int) date('n', $ts);

    $ecodYears[$year][$month][] = [
        'title'    => (string) $ecodRow['title'],
        'created'  => $ts,
        'comments' => (int) $ecodRow['commentsNum'],
        'url'      => \Typecho\Router::url('post', $ecodRow, $this->options->index),
    ];
    $ecodTotal++;

    if ($ecodMinYear === 0 || $year < $ecodMinYear) {
        $ecodMinYear = $year;
    }
    if ($year > $ecodMaxYear) {
        $ecodMaxYear = $year;
    }
}

krsort($ecodYears);
$ecodFirstYear = $ecodYears ? (int) array_key_first($ecodYears) : 0;

$ecodMeta = '<span>' . ecod_icon('inbox', 13) . $ecodTotal . ' 篇</span>';
if ($ecodMinYear > 0) {
    $ecodMeta .= '<span>' . ecod_icon('calendar', 13)
        . ($ecodMinYear === $ecodMaxYear ? $ecodMaxYear : $ecodMinYear . ' – ' . $ecodMaxYear)
        . '</span>';
}
?>
<main class="ecod_main" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a class="ecod_crumb__back" href="javascript:history.back()" aria-label="返回"><?php echo ecod_icon('chevron', 13); ?></a>
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true">/</span>
        <span aria-current="page"><?php $this->title(); ?></span>
    </nav>

    <?php ecod_page_hero('归档', (string) $this->title, '按时间倒序排列的全部文章，点年份展开。', $ecodMeta); ?>

    <?php if ($ecodError !== ''): ?>
        <?php ecod_empty('alert', $ecodError, '请稍后重试，或检查数据库连接'); ?>
    <?php elseif (!$ecodYears): ?>
        <?php ecod_empty('inbox', '还没有文章', '发布第一篇之后，这里会自动生成时间轴'); ?>
    <?php else: ?>
        <div class="ecod_arch">
            <?php foreach ($ecodYears as $ecodYear => $ecodMonths): ?>
                <?php
                $ecodYearCount = 0;
                foreach ($ecodMonths as $ecodList) {
                    $ecodYearCount += count($ecodList);
                }
                krsort($ecodMonths);
                ?>
                <details class="ecod_arch__year"<?php echo (int) $ecodYear === $ecodFirstYear ? ' open' : ''; ?>>
                    <summary>
                        <b><?php echo (int) $ecodYear; ?></b>
                        <span class="ecod_arch__num"><?php echo $ecodYearCount; ?> 篇</span>
                        <span class="ecod_arch__chev" aria-hidden="true"><?php echo ecod_icon('chevron', 15); ?></span>
                    </summary>
                    <div class="ecod_arch__body">
                        <?php foreach ($ecodMonths as $ecodMonth => $ecodItems): ?>
                            <section class="ecod_arch__month">
                                <h3><span><?php echo (int) $ecodMonth; ?> 月</span><em><?php echo count($ecodItems); ?></em></h3>
                                <ol class="ecod_arch__list">
                                    <?php foreach ($ecodItems as $ecodItem): ?>
                                        <li class="ecod_arch__item">
                                            <time datetime="<?php echo date('c', $ecodItem['created']); ?>"><?php echo date('m-d', $ecodItem['created']); ?></time>
                                            <a href="<?php echo htmlspecialchars($ecodItem['url'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ecodItem['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php if ($ecodItem['comments'] > 0): ?>
                                                <span class="ecod_arch__cmt"><?php echo ecod_icon('comment', 11); ?><?php echo $ecodItem['comments']; ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>


    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
