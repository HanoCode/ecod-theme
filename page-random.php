<?php
/**
 * 随机文章：访问即 302 跳到一篇随机文章
 *
 * @package custom
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 *
 * 在后台「页面 → 新建页面 → 自定义模板」里选择「随机文章」即可，例如建一个
 * 标题为「随便看看」、地址为 random 的页面，访问 /index.php/random.html 就会
 * 立刻跳到一篇随机文章。
 *
 * 实现要点：
 * - 先用 COUNT 取总数，再用 OFFSET 取一条，避免 ORDER BY RAND() 在大表上全表扫描
 * - cookie 记住上一篇，文章数 > 1 时重试，避免连点两次还在原地
 * - 跳转发生在任何 HTML 输出之前，所以不能提前 need('public/header.php')
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodDb = \Typecho\Db::get();
$ecodTotal = 0;
$ecodPick = null;
$ecodError = '';

try {
    $ecodRow = $ecodDb->fetchRow(
        $ecodDb->select('COUNT(*) AS num')->from('table.contents')
            ->where('type = ?', 'post')
            ->where('status = ?', 'publish')
            ->where('created < ?', time())
            ->where("password IS NULL OR password = ''")
    );
    $ecodTotal = $ecodRow ? (int) $ecodRow['num'] : 0;

    if ($ecodTotal > 0) {
        $ecodLast = isset($_COOKIE['ecod_rand']) ? (int) $_COOKIE['ecod_rand'] : 0;
        $ecodTries = $ecodTotal > 1 ? 3 : 1;

        for ($ecodI = 0; $ecodI < $ecodTries; $ecodI++) {
            $ecodOffset = $ecodTotal > 1 ? random_int(0, $ecodTotal - 1) : 0;

            $ecodPick = $ecodDb->fetchRow(
                $ecodDb->select('cid', 'slug', 'type', 'created')
                    ->from('table.contents')
                    ->where('type = ?', 'post')
                    ->where('status = ?', 'publish')
                    ->where('created < ?', time())
                    ->where("password IS NULL OR password = ''")
                    ->order('created', \Typecho\Db::SORT_DESC)
                    ->offset($ecodOffset)
                    ->limit(1)
            );

            if ($ecodPick && (int) $ecodPick['cid'] !== $ecodLast) {
                break;
            }
        }
    }
} catch (\Throwable $e) {
    $ecodError = '读取文章失败';
}

if ($ecodPick) {
    $ecodUrl = \Typecho\Router::url('post', $ecodPick, $this->options->index);

    if ($ecodUrl && $ecodUrl !== '#') {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('X-Robots-Tag: noindex, nofollow', true);
        }

        setcookie('ecod_rand', (string) $ecodPick['cid'], [
            'expires'  => time() + 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $this->response->redirect($ecodUrl);
        exit;
    }

    $ecodError = '无法生成文章地址';
}

/* 走到这里说明没有可跳转的文章，输出一个提示页 */
$this->need('public/header.php');
?>
<main class="ecod_main" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a class="ecod_crumb__back" href="javascript:history.back()" aria-label="返回"><?php echo ecod_icon('chevron', 13); ?></a>
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true">/</span>
        <span aria-current="page"><?php $this->title(); ?></span>
    </nav>

    <?php ecod_page_hero('随机', (string) $this->title, '每次点开都带你去一篇没读过的文章。'); ?>

    <?php
    if ($ecodError !== '') {
        ecod_empty('shuffle', $ecodError, '请稍后重试，或检查数据库连接', $this->options->siteUrl, '回到首页');
    } else {
        ecod_empty('shuffle', '还没有可跳转的文章', '发布文章后，这个页面会随机带你去看一篇', $this->options->siteUrl, '回到首页');
    }
    ?>


    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
