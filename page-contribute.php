<?php
/**
 * 前端投稿
 *
 * @package custom
 * @author  Ecod
 * @since   2026-10-30
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 *
 * 在后台「页面 → 新建页面 → 自定义模板」里选择「投稿」即可（工具 → 特色页可一键创建）。
 * 登录用户提交文章进入后台「待审核」列表，管理员点发布即上线；
 * 「个人中心 → 我的文章」里的待审核 / 草稿稿可通过 ?edit=<cid> 回到本页修改。
 * 开关与限制在「设置 → 互动 → 前端投稿」里配置。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');
require_once __DIR__ . '/core/user/account.php';
require_once __DIR__ . '/core/user/meta.php';

/* 投稿开关与限制 */
$ecodCtbOpen = ecod_option('EContribute', 'off') === 'on';
$ecodCtbGroup = (string) ecod_option('EContributeGroup', 'contributor');
$ecodCtbNote = trim((string) ecod_option('EContributeNote', ''));

/* 登录态 */
$ecodUser = null;
$ecodLogged = false;
try {
    $ecodUser = \Widget\User::alloc();
    $ecodLogged = (bool) $ecodUser->hasLogin();
} catch (\Throwable $e) {
}

/* 用户组门槛：subscriber=所有登录用户，contributor=投稿者及以上 */
$ecodCtbAllowed = false;
if ($ecodLogged) {
    try {
        $ecodCtbAllowed = $ecodUser->pass($ecodCtbGroup === 'subscriber' ? 'subscriber' : 'contributor', true);
    } catch (\Throwable $e) {
    }
}

/* 可投稿分类（与 api.php 的校验同源：顶级分类 ∩ 指定清单） */
$ecodCtbCats = [];
if ($ecodCtbOpen) {
    try {
        $ecodCtbDb = \Typecho\Db::get();
        $ecodCtbRows = $ecodCtbDb->fetchAll(
            $ecodCtbDb->select('mid', 'name')->from('table.metas')
                ->where('type = ?', 'category')->where('parent = ?', 0)
                ->order('order', \Typecho\Db::SORT_ASC)
        );
        $ecodCtbRestrict = trim((string) ecod_option('EContributeCats', ''));
        $ecodCtbAllowIds = [];
        if ($ecodCtbRestrict !== '') {
            foreach (explode(',', $ecodCtbRestrict) as $ecodCtbOne) {
                $ecodCtbOne = (int) trim($ecodCtbOne);
                if ($ecodCtbOne > 0) {
                    $ecodCtbAllowIds[$ecodCtbOne] = true;
                }
            }
        }
        foreach ($ecodCtbRows as $ecodCtbRow) {
            if ($ecodCtbAllowIds && empty($ecodCtbAllowIds[(int) $ecodCtbRow['mid']])) {
                continue;
            }
            $ecodCtbCats[] = ['mid' => (int) $ecodCtbRow['mid'], 'name' => (string) $ecodCtbRow['name']];
        }
    } catch (\Throwable $e) {
    }
}

/* 编辑模式：?edit=<cid> 只能打开自己的待审核 / 草稿稿 */
$ecodCtbEdit = null;
$ecodEditCid = (int) $this->request->filter('int')->get('edit');
if ($ecodLogged && $ecodCtbAllowed && $ecodEditCid > 0) {
    try {
        $ecodCtbDb = \Typecho\Db::get();
        $ecodCtbEdit = $ecodCtbDb->fetchRow(
            $ecodCtbDb->select('cid', 'title', 'text')->from('table.contents')
                ->where('cid = ?', $ecodEditCid)
                ->where('authorId = ?', (int) $ecodUser->uid)
                ->where('type = ?', 'post')
                ->where('status IN (\'waiting\',\'draft\')')
                ->limit(1)
        );
        if ($ecodCtbEdit) {
            /* 回填分类 / 标签 / 封面 */
            $ecodCtbEdit['category'] = 0;
            $ecodCtbEdit['tags'] = [];
            $ecodCtbEdit['cover'] = '';
            $ecodCtbRels = $ecodCtbDb->fetchAll(
                $ecodCtbDb->select('m.mid', 'm.name', 'm.type')->from('table.relationships')
                    ->join('table.metas', 'table.relationships.mid = table.metas.mid')
                    ->where('table.relationships.cid = ?', (int) $ecodCtbEdit['cid'])
            );
            foreach ($ecodCtbRels as $ecodCtbRel) {
                if ((string) $ecodCtbRel['type'] === 'category' && $ecodCtbEdit['category'] === 0) {
                    $ecodCtbEdit['category'] = (int) $ecodCtbRel['mid'];
                } elseif ((string) $ecodCtbRel['type'] === 'tag') {
                    $ecodCtbEdit['tags'][] = (string) $ecodCtbRel['name'];
                }
            }
            $ecodCtbThumb = $ecodCtbDb->fetchRow(
                $ecodCtbDb->select('str_value')->from('table.fields')
                    ->where('cid = ?', (int) $ecodCtbEdit['cid'])->where('name = ?', 'thumb')->limit(1)
            );
            if ($ecodCtbThumb) {
                $ecodCtbEdit['cover'] = (string) $ecodCtbThumb['str_value'];
            }
            /* 剥掉 markdown 前缀再回填 */
            $ecodCtbEdit['text'] = preg_replace('/^<!--markdown-->/', '', (string) $ecodCtbEdit['text']);
        }
    } catch (\Throwable $e) {
        $ecodCtbEdit = null;
    }
}

$ecodLoginUrl = ecod_login_url();
$ecodUcUrl = ecod_user_center_url();
?>
<main class="ecod_main" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a class="ecod_crumb__back" href="javascript:history.back()" aria-label="返回"><?php echo ecod_icon('chevron', 13); ?></a>
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true">/</span>
        <span aria-current="page"><?php $this->title(); ?></span>
    </nav>

    <?php ecod_page_hero('投稿', (string) $this->title, '把你写的好东西投给本站，审核通过后发布在首页。'); ?>

    <div class="ecod_ctb">
        <?php if (!$ecodCtbOpen): ?>
            <div class="ecod_ctb__gate">
                <?php echo ecod_icon('inbox', 26); ?>
                <h2>投稿暂未开放</h2>
                <p>站长还没有开启前端投稿，欢迎通过评论交流。</p>
            </div>
        <?php elseif (!$ecodLogged): ?>
            <div class="ecod_ctb__gate">
                <?php echo ecod_icon('lock', 26); ?>
                <h2>登录后投稿</h2>
                <p>投稿需要登录账号，文章会记录在你的名下。</p>
                <div class="ecod_ctb__gate-ops">
                    <a class="ecod-btn" data-ecod-login-open data-no-pjax
                       href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>?referer=<?php echo rawurlencode($this->permalink); ?>">登录</a>
                    <?php if ((int) $this->options->allowRegister === 1): ?>
                        <a class="ecod-btn ecod-btn--ghost" href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>?referer=<?php echo rawurlencode($this->permalink); ?>">注册账号</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif (!$ecodCtbAllowed): ?>
            <div class="ecod_ctb__gate">
                <?php echo ecod_icon('lock', 26); ?>
                <h2>当前用户组暂不能投稿</h2>
                <p>投稿功能对特定用户组开放，先通过评论与互动活跃起来吧。</p>
            </div>
        <?php elseif (!$ecodCtbCats): ?>
            <div class="ecod_ctb__gate">
                <?php echo ecod_icon('folder', 26); ?>
                <h2>暂无可投稿的分类</h2>
                <p>站长还没有配置可投稿的分类，稍后再来看看。</p>
            </div>
        <?php else: ?>
            <?php if ($ecodCtbEdit): ?>
                <p class="ecod_ctb__editing">
                    <?php echo ecod_icon('edit', 13); ?>
                    正在修改你的投稿「<?php echo htmlspecialchars(mb_substr((string) $ecodCtbEdit['title'], 0, 30, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>」
                    · <a href="<?php echo htmlspecialchars((string) $this->permalink, ENT_QUOTES, 'UTF-8'); ?>">放弃修改，写新稿</a>
                </p>
            <?php endif; ?>

            <?php if ($ecodCtbNote !== ''): ?>
                <div class="ecod_ctb__note">
                    <?php echo ecod_icon('info', 14); ?>
                    <p><?php echo nl2br(htmlspecialchars($ecodCtbNote, ENT_QUOTES, 'UTF-8')); ?></p>
                </div>
            <?php endif; ?>

            <form class="ecod_ctb__form" data-ecod-contribute
                  data-ecod-api="<?php echo htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8'); ?>"
                  data-ecod-token="<?php echo htmlspecialchars(ecod_account_token(), ENT_QUOTES, 'UTF-8'); ?>"
                  data-ecod-cid="<?php echo $ecodCtbEdit ? (int) $ecodCtbEdit['cid'] : 0; ?>"
                  novalidate>
                <label class="ecod_ctb__field">
                    <span>标题 <i>*</i></span>
                    <input type="text" name="title" required maxlength="100" autocomplete="off"
                           placeholder="一句话说清这篇文章讲什么（3-100 字）"
                           value="<?php echo $ecodCtbEdit ? htmlspecialchars((string) $ecodCtbEdit['title'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                </label>

                <div class="ecod_ctb__row">
                    <label class="ecod_ctb__field">
                        <span>分类 <i>*</i></span>
                        <select name="category" required>
                            <option value="">选择分类…</option>
                            <?php foreach ($ecodCtbCats as $ecodCtbCat): ?>
                                <option value="<?php echo (int) $ecodCtbCat['mid']; ?>"<?php
                                    echo $ecodCtbEdit && (int) $ecodCtbEdit['category'] === (int) $ecodCtbCat['mid'] ? ' selected' : '';
                                ?>><?php echo htmlspecialchars($ecodCtbCat['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="ecod_ctb__field">
                        <span>标签 <em>选填，逗号分隔</em></span>
                        <input type="text" name="tags" maxlength="120" autocomplete="off"
                               placeholder="例如：PHP, Typecho, 教程（最多 5 个）"
                               value="<?php echo $ecodCtbEdit ? htmlspecialchars(implode(',', $ecodCtbEdit['tags']), ENT_QUOTES, 'UTF-8') : ''; ?>">
                    </label>
                </div>

                <label class="ecod_ctb__field">
                    <span>封面图 <em>选填</em></span>
                    <input type="url" name="cover" maxlength="300" autocomplete="off" placeholder="https://…（jpg / png / webp）"
                           value="<?php echo $ecodCtbEdit ? htmlspecialchars((string) $ecodCtbEdit['cover'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                    <span class="ecod_ctb__cover" data-ecod-ctb-cover hidden>
                        <img src="" alt="封面预览" loading="lazy" decoding="async">
                    </span>
                </label>

                <label class="ecod_ctb__field">
                    <span>正文 <i>*</i> <em>支持 Markdown</em></span>
                    <textarea name="text" rows="14" required minlength="10"
                              placeholder="正文至少 10 个字。支持 Markdown 与本主题短代码，如 [note type=&quot;tip&quot;]提示[/note]、[download]…[/download]"><?php
                        echo $ecodCtbEdit ? htmlspecialchars((string) $ecodCtbEdit['text'], ENT_QUOTES, 'UTF-8') : '';
                    ?></textarea>
                </label>

                <input type="text" name="website" class="ecod_ctb__hp" tabindex="-1" autocomplete="off" aria-hidden="true">

                <p class="ecod_ctb__msg" data-ecod-ctb-msg hidden></p>

                <div class="ecod_ctb__ops">
                    <button type="submit" class="ecod-btn ecod_ctb__submit" data-ecod-ctb-submit>
                        <?php echo $ecodCtbEdit ? '保存修改' : '提交投稿'; ?>
                    </button>
                    <span class="ecod_ctb__tip">提交后进入待审核，进度可在<?php
                        echo $ecodUcUrl !== ''
                            ? '<a href="' . htmlspecialchars($ecodUcUrl, ENT_QUOTES, 'UTF-8') . '">个人中心 → 我的文章</a>'
                            : '个人中心';
                    ?>查看。</span>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
