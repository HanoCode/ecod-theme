<?php
/**
 * 前台登录页：登录 / 注册 / 找回密码 三合一
 *
 * @package custom
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 *
 * 在后台「页面 → 新建页面 → 自定义模板」里选择「登录」即可。
 * 登录与后台共用同一套校验（Widget\User::login）；
 * 注册与找回密码走 api.php 的 register / send_code / reset_password。
 * 「注册」标签仅在后台开启「允许注册」时出现；
 * 「找回密码」仅在后台开启「账户邮件」并配好 SMTP 后出现。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');
require_once __DIR__ . '/core/account.php';
require_once __DIR__ . '/core/oauth.php';

$ecodLogged = false;
$ecodUcUrl = ecod_user_center_url();
try {
    $ecodLogged = (bool) \Widget\User::alloc()->hasLogin();
} catch (\Throwable $e) {
}

$ecodReferer = '';
if (isset($_GET['referer'])) {
    $ecodReferer = (string) $_GET['referer'];
    /* 只接受站内地址 */
    if ($ecodReferer !== '' && preg_match('#^https?://#i', $ecodReferer)) {
        $ecodHost = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        $refHost = strtolower(preg_replace('/:\d+$/', '', (string) (parse_url($ecodReferer, PHP_URL_HOST) ?? '')));
        $ecodReferer = ($ecodHost !== '' && $ecodHost === $refHost)
            ? (string) (parse_url($ecodReferer, PHP_URL_PATH) ?: '')
            : '';
    }
    if ($ecodReferer !== '' && ($ecodReferer[0] !== '/' || substr($ecodReferer, 0, 2) === '//')) {
        $ecodReferer = '';
    }
}

/* 游客令牌只依赖站点 secret，同一浏览器稳定，可安全用作表单 CSRF */
$ecodToken = function_exists('ecod_account_token') ? ecod_account_token() : '';
$ecodAllowRegister = (int) $this->options->allowRegister === 1;
$ecodAllowReset = function_exists('ecod_account_mail_ready') ? ecod_account_mail_ready() : false;

/* 第三方登录：GitHub（后台配置凭据后启用） */
$ecodOAuthGithub = function_exists('ecod_oauth_config') ? (bool) ecod_oauth_config('github') : false;

/* OAuth 回跳错误提示（如"该第三方账号未绑定本站用户"） */
$ecodOauthError = '';
if (isset($_GET['oauth_error'])) {
    $ecodOauthError = trim((string) $_GET['oauth_error']);
    if (mb_strlen($ecodOauthError, 'UTF-8') > 80) {
        $ecodOauthError = '';
    }
}
$ecodOauthStart = $ecodOAuthGithub
    ? rtrim((string) $this->options->siteUrl, '/') . '/usr/themes/ecod-theme/api.php?action=oauth_start&platform=github&referer='
        . rawurlencode($ecodReferer !== '' ? $ecodReferer : '/')
    : '';
/* 登录页背景图（后台「外观」分组可配） */
$ecodLoginBg = trim((string) ecod_option('ELoginBg', ''));
?>
<main class="ecod_main ecod_main--login" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

    <?php if ($ecodLoginBg !== ''): ?>
        <div class="ecod_login__bg" style="background-image:url('<?php echo htmlspecialchars($ecodLoginBg, ENT_QUOTES, 'UTF-8'); ?>');" aria-hidden="true"></div>
    <?php endif; ?>

    <div class="ecod_login ecod_login--stage">
        <?php if ($ecodLogged): ?>
            <div class="ecod_login__card">
                <span class="ecod_login__logo" aria-hidden="true"><?php echo ecod_icon('check', 22); ?></span>
                <h1 class="ecod_login__title">已登录</h1>
                <p class="ecod_login__desc">当前浏览器已登录，无需重复操作。</p>
                <div class="ecod_login__ops">
                    <?php if ($ecodUcUrl !== ''): ?>
                        <a class="ecod-btn" href="<?php echo htmlspecialchars($ecodUcUrl, ENT_QUOTES, 'UTF-8'); ?>">前往个人中心</a>
                    <?php endif; ?>
                    <a class="ecod-btn ecod-btn--ghost" href="<?php $this->options->siteUrl(); ?>">返回首页</a>
                </div>
            </div>
        <?php else: ?>
            <div class="ecod_login__card" data-ecod-auth
                 data-ecod-token="<?php echo htmlspecialchars($ecodToken, ENT_QUOTES, 'UTF-8'); ?>">

                <span class="ecod_login__logo" aria-hidden="true"><?php echo ecod_icon('user', 22); ?></span>
                <h1 class="ecod_login__title"><?php $this->title(); ?></h1>
                <p class="ecod_login__desc">使用站点账号登录，同步你的收藏、点赞与浏览历史。</p>

                <?php if ($ecodAllowRegister || $ecodAllowReset): ?>
                    <div class="ecod_login__tabs" role="tablist" aria-label="账户操作">
                        <button type="button" class="is-active" role="tab" aria-selected="true"
                                id="ecod-auth-tab-login" aria-controls="ecod-auth-panel-login" tabindex="0"
                                data-ecod-auth-tab="login">登录</button>
                        <?php if ($ecodAllowRegister): ?>
                            <button type="button" role="tab" aria-selected="false" tabindex="-1"
                                    id="ecod-auth-tab-register" aria-controls="ecod-auth-panel-register"
                                    data-ecod-auth-tab="register">注册</button>
                        <?php endif; ?>
                        <?php if ($ecodAllowReset): ?>
                            <button type="button" role="tab" aria-selected="false" tabindex="-1"
                                    id="ecod-auth-tab-forgot" aria-controls="ecod-auth-panel-forgot"
                                    data-ecod-auth-tab="forgot">找回密码</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($ecodOauthError !== ''): ?>
                    <p class="ecod_login__msg is-bad" role="alert"><?php
                        echo htmlspecialchars($ecodOauthError, ENT_QUOTES, 'UTF-8');
                    ?></p>
                <?php endif; ?>

                <input type="hidden" name="referer" value="<?php echo htmlspecialchars($ecodReferer, ENT_QUOTES, 'UTF-8'); ?>"
                       data-ecod-auth-referer>

                <!-- 登录 -->
                <form class="ecod_login__form" method="post" action="#" data-ecod-login
                      data-ecod-home="<?php $this->options->siteUrl(); ?>"
                      id="ecod-auth-panel-login"
                      <?php if ($ecodAllowRegister || $ecodAllowReset): ?>aria-labelledby="ecod-auth-tab-login"<?php endif; ?>
                      data-ecod-auth-panel="login">
                    <label class="ecod_login__field">
                        <span>用户名或邮箱</span>
                        <input type="text" name="name" required autocomplete="username" placeholder="用户名或邮箱">
                    </label>
                    <label class="ecod_login__field">
                        <span>密码</span>
                        <input type="password" name="password" required autocomplete="current-password" placeholder="密码">
                    </label>
                    <label class="ecod_login__field">
                        <span>验证码</span>
                        <span class="ecod_login__cap">
                            <input type="text" name="captcha" required autocomplete="off" spellcheck="false"
                                   inputmode="latin" maxlength="4" placeholder="4 位字符">
                            <img class="ecod_login__capimg" data-ecod-captcha
                                 src="<?php echo htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8'); ?>?action=captcha&amp;t=<?php echo time(); ?>"
                                 alt="验证码，点击刷新" width="96" height="40">
                        </span>
                    </label>
                    <label class="ecod_login__remember">
                        <input type="checkbox" name="remember" value="1">
                        <span>30 天内自动登录</span>
                    </label>
                    <p class="ecod_login__msg" data-ecod-login-msg hidden></p>
                    <button type="submit" class="ecod-btn ecod_login__submit">登 录</button>
                    <?php if ($ecodAllowReset): ?>
                        <p class="ecod_login__more"><a href="#" data-ecod-auth-goto="forgot">忘记密码？</a></p>
                    <?php endif; ?>
                </form>

                <?php if ($ecodAllowRegister): ?>
                <!-- 注册 -->
                <form class="ecod_login__form" method="post" action="#" data-ecod-auth-form="register"
                      id="ecod-auth-panel-register" aria-labelledby="ecod-auth-tab-register"
                      data-ecod-auth-panel="register" hidden>
                    <label class="ecod_login__field">
                        <span>用户名</span>
                        <input type="text" name="name" required autocomplete="username"
                               placeholder="2-32 位中文、字母、数字或下划线">
                    </label>
                    <label class="ecod_login__field">
                        <span>邮箱</span>
                        <input type="email" name="mail" required autocomplete="email"
                               placeholder="用于找回密码，不会公开">
                    </label>
                    <label class="ecod_login__field">
                        <span>密码</span>
                        <input type="password" name="password" required autocomplete="new-password"
                               minlength="8" maxlength="64" placeholder="8-64 位">
                    </label>
                    <label class="ecod_login__field">
                        <span>确认密码</span>
                        <input type="password" name="confirm" required autocomplete="new-password"
                               minlength="8" maxlength="64" placeholder="再输入一次">
                    </label>
                    <p class="ecod_login__msg" data-ecod-auth-msg="register" hidden></p>
                    <button type="submit" class="ecod-btn ecod_login__submit">注 册</button>
                    <p class="ecod_login__more">注册即代表同意本站点的用户协议与隐私政策。</p>
                </form>
                <?php endif; ?>

                <?php if ($ecodAllowReset): ?>
                <!-- 找回密码：先发验证码，再设新密码 -->
                <div data-ecod-auth-panel="forgot" id="ecod-auth-panel-forgot"
                     aria-labelledby="ecod-auth-tab-forgot" hidden>
                    <form class="ecod_login__form" method="post" action="#" data-ecod-auth-form="forgot_send">
                        <label class="ecod_login__field">
                            <span>绑定邮箱</span>
                            <input type="email" name="mail" required autocomplete="email"
                                   placeholder="注册时填写的邮箱">
                        </label>
                        <p class="ecod_login__msg" data-ecod-auth-msg="forgot_send" hidden></p>
                        <button type="submit" class="ecod-btn ecod_login__submit">发送验证码</button>
                    </form>

                    <form class="ecod_login__form" method="post" action="#" data-ecod-auth-form="forgot_reset"
                          data-ecod-forgot-step2 hidden>
                        <label class="ecod_login__field">
                            <span>邮箱验证码</span>
                            <input type="text" name="code" required inputmode="numeric" pattern="\d{6}"
                                   maxlength="6" placeholder="6 位数字，15 分钟内有效">
                        </label>
                        <label class="ecod_login__field">
                            <span>新密码</span>
                            <input type="password" name="password" required autocomplete="new-password"
                                   minlength="8" maxlength="64" placeholder="8-64 位">
                        </label>
                        <label class="ecod_login__field">
                            <span>确认新密码</span>
                            <input type="password" name="confirm" required autocomplete="new-password"
                                   minlength="8" maxlength="64" placeholder="再输入一次">
                        </label>
                        <p class="ecod_login__msg" data-ecod-auth-msg="forgot_reset" hidden></p>
                        <button type="submit" class="ecod-btn ecod_login__submit">重置密码</button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($ecodOAuthGithub): ?>
                <!-- 第三方登录 -->
                <div class="ecod_login__oauth">
                    <span class="ecod_login__oauth-line" aria-hidden="true"></span>
                    <span>第三方登录</span>
                    <span class="ecod_login__oauth-line" aria-hidden="true"></span>
                </div>
                <a class="ecod_login__oauthbtn" href="<?php echo htmlspecialchars($ecodOauthStart, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo ecod_icon('github', 17); ?>
                    <span>使用 GitHub 登录</span>
                </a>
                <?php endif; ?>

                <p class="ecod_login__more ecod_login__more--home"><a href="<?php $this->options->siteUrl(); ?>">返回首页</a></p>
            </div>
        <?php endif; ?>
    </div>


    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php $this->need('public/footer.php'); ?>
