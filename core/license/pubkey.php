<?php

/**
 * Ecod Theme · 授权内置公钥
 *
 * 与作者端 EcodLicenser 密钥对中的公钥 kid/pub 一致；验签一律用这个内置公钥，
 * 端点返回的 pub 仅用于展示，绝不参与验签。
 *
 * 公钥来源：站点 MySQL typecho_options.EcodLicenserKeys（kid=7d9abc93）。
 * 重新生成作者端密钥时，这里必须同步更新，否则所有授权失效。
 *
 * @package ecod-theme
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

define('ECOD_LIC_PUB', '4OoBOPO3b5ESTq2WJVjYTVINjnWOBjo1oWmMxbNek6A=');
define('ECOD_LIC_KID', '7d9abc93');
define('ECOD_LIC_ENDPOINT_DEFAULT', 'https://ecod.cc/action/ecod-license-verify');
define('ECOD_LIC_GRACE_DEFAULT', 14);
define('ECOD_LIC_TRIAL_DAYS', 7);
define('ECOD_LIC_REBIND_LIMIT_DEFAULT', 2);
define('ECOD_LIC_OFFLINE_MAX', 90); // 离线宽限最长天数（grace 上限）
