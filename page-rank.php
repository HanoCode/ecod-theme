<?php
/**
 * 评论墙：按留言数排行的活跃读者榜
 *
 * @package custom
 *
 * 在后台「页面 → 新建页面 → 自定义模板」里选择「评论墙」即可。
 * 按邮箱聚合已通过评论数，排除站长本人。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');

$ecodDb = \Typecho\Db::get();
$ecodLimit = 30;
$ecodList = [];
$ecodError = '';

try {
    $ecodRows = $ecodDb->fetchAll(
        $ecodDb->select('mail', 'COUNT(*) AS num', 'MAX(created) AS last')
            ->from('table.comments')
            ->where('status = ?', 'approved')
            ->where('type = ?', 'comment')
            ->where('mail <> ?', '')
            ->group('mail')
            ->order('num', \Typecho\Db::SORT_DESC)
            ->limit($ecodLimit)
    );

    /* 站长邮箱不参与排名，单独取一次 */
    $ecodOwnerMails = [];
    try {
        $ecodOwners = $ecodDb->fetchAll(
            $ecodDb->select('mail')->from('table.users')->where('uid = ?', 1)
        );
        foreach ($ecodOwners as $ecodOwner) {
            if (!empty($ecodOwner['mail'])) {
                $ecodOwnerMails[] = strtolower(trim((string) $ecodOwner['mail']));
            }
        }
    } catch (\Throwable $e) {
    }

    /* 昵称取该邮箱最近一条评论的作者名 */
    foreach ($ecodRows as $ecodRow) {
        $mail = strtolower(trim((string) $ecodRow['mail']));
        if ($mail === '' || in_array($mail, $ecodOwnerMails, true)) {
            continue;
        }

        $name = '';
        $url = '';
        try {
            $last = $ecodDb->fetchRow(
                $ecodDb->select('author', 'url')->from('table.comments')
                    ->where('mail = ?', (string) $ecodRow['mail'])
                    ->where('status = ?', 'approved')
                    ->order('created', \Typecho\Db::SORT_DESC)
                    ->limit(1)
            );
            if ($last) {
                $name = (string) $last['author'];
                $url = (string) $last['url'];
            }
        } catch (\Throwable $e) {
        }

        $ecodList[] = [
            'mail'   => $mail,
            'name'   => $name !== '' ? $name : '匿名读者',
            'url'    => $url,
            'count'  => (int) $ecodRow['num'],
            'last'   => (int) $ecodRow['last'],
            'avatar' => ecod_avatar_prefix() . md5($mail) . '?s=72&d=mp&r=G',
        ];
    }
} catch (\Throwable $e) {
    $ecodError = '读取评论数据失败';
}

$ecodMeta = '<span>' . ecod_icon('user', 13) . count($ecodList) . ' 位读者</span>';
$ecodTotalComments = 0;
foreach ($ecodList as $ecodOne) {
    $ecodTotalComments += $ecodOne['count'];
}
if ($ecodTotalComments > 0) {
    $ecodMeta .= '<span>' . ecod_icon('comment', 13) . $ecodTotalComments . ' 条评论</span>';
}
?>
<main class="ecod_main" id="ecod-main">

    <nav class="ecod_crumb" aria-label="面包屑">
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true"><?php echo ecod_icon('chevron', 11); ?></span>
        <span aria-current="page"><?php $this->title(); ?></span>
    </nav>

    <?php ecod_page_hero('评论墙', (string) $this->title, '在这里留下过声音的人，按评论数排序。', $ecodMeta); ?>

    <?php if ($ecodError !== ''): ?>
        <div class="ecod_empty">
            <div class="ecod_empty__icon" aria-hidden="true"><?php echo ecod_icon('alert', 30); ?></div>
            <h3><?php echo $ecodError; ?></h3>
            <p>请稍后重试</p>
        </div>
    <?php elseif (!$ecodList): ?>
        <div class="ecod_empty">
            <div class="ecod_empty__icon" aria-hidden="true"><?php echo ecod_icon('comment', 30); ?></div>
            <h3>还没有人留言</h3>
            <p>成为第一个在这里说话的人</p>
        </div>
    <?php else: ?>
        <div class="ecod_readers">
            <?php foreach ($ecodList as $ecodIndex => $ecodPerson): ?>
                <?php
                $ecodRank = $ecodIndex + 1;
                $ecodMedal = '';
                if ($ecodRank === 1) {
                    $ecodMedal = ' is-gold';
                } elseif ($ecodRank === 2) {
                    $ecodMedal = ' is-silver';
                } elseif ($ecodRank === 3) {
                    $ecodMedal = ' is-bronze';
                }
                ?>
                <article class="ecod_reader<?php echo $ecodMedal; ?>">
                    <span class="ecod_reader__rank"><?php echo $ecodRank; ?></span>
                    <img class="ecod_reader__avatar" src="<?php echo htmlspecialchars($ecodPerson['avatar'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($ecodPerson['name'], ENT_QUOTES, 'UTF-8'); ?>" width="56" height="56" loading="lazy" referrerpolicy="no-referrer">
                    <div class="ecod_reader__main">
                        <?php if ($ecodPerson['url'] !== ''): ?>
                            <a class="ecod_reader__name" href="<?php echo htmlspecialchars($ecodPerson['url'], ENT_QUOTES, 'UTF-8'); ?>"
                               rel="external nofollow noopener" target="_blank"><?php echo htmlspecialchars($ecodPerson['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                        <?php else: ?>
                            <b class="ecod_reader__name"><?php echo htmlspecialchars($ecodPerson['name'], ENT_QUOTES, 'UTF-8'); ?></b>
                        <?php endif; ?>
                        <small><?php echo ecod_relative_time($ecodPerson['last']); ?>说过话</small>
                    </div>
                    <span class="ecod_reader__count"><?php echo $ecodPerson['count']; ?><em>条</em></span>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
