<?php
/** A 组二次核实：动态拼接键名（'EVip'.$i.'Name'）也算消费 */
$root = dirname(__DIR__);
$suspects = ['EThumbServer','EMediaStorage','EMediaDualWrite','EMediaMaxBytes','EWebpConvert','EWebpQuality','EWebpKeepOriginal','EMediaAllowSvg','EMediaAllowExt','EMediaOssKey','EMediaOssSecret','EMediaOssBucket','EMediaOssEndpoint','EMediaOssDomain','EMediaCosKey','EMediaCosSecret','EMediaCosBucket','EMediaCosRegion','EMediaCosDomain','EMediaS3Key','EMediaS3Secret','EMediaS3Bucket','EMediaS3Region','EMediaS3Endpoint','EMediaS3Domain','EFriendApply','EStats','EStatsMine','EStatsKeep','ECmtGap','ECmtHour','ECmtDay','ECommentMail','ECommentMailHost','ECommentMailPort','ECommentMailSecure','ECommentMailAccount','ECommentMailPassword','ECommentMailFrom','ECommentMailTo','EPush','EPushChannel','EPushKey','EPushExtra','EReplyMail','EAccountMail','EFriendMailSubject','EFriendMailBody','EFriendPassSubject','EFriendPassBody','EOAuthGithub','EOAuthGithubId','EOAuthGithubSecret','EOAuthWeixin','EOAuthWeixinId','EOAuthWeixinSecret','EOAuthQQ','EOAuthQQId','EOAuthQQSecret','EOAuthWeibo','EOAuthWeiboId','EOAuthWeiboSecret','EOAuthGitee','EOAuthGiteeId','EOAuthGiteeSecret','EOAuthBindMail','EOAuthRegister','ECrComment','ECrCommentCap','ECrLikeRecv','ECrLikeCap','ECrPost','ECrPostCap','EBaidu','EPayAdminUrl','EVip1Name','EVip1Month','EVip1Quarter','EVip1Year','EVip1Forever','EVip2Name','EVip2Month','EVip2Quarter','EVip2Year','EVip2Forever','EAdSelfPriceHomeTop','EAdSelfPriceSidebar','EAdSelfPricePostBottom','EAdSelfPriceAiPanel','ENavSets','ETopics'];

/* 拼接源码全文（排除 dist/backup/temp） */
$all = '';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = $f->getPathname();
    if (strpos($p, '/.') !== false || strpos($p, '/dist/') !== false || strpos($p, '/.backup') !== false || strpos($p, '/temp/') !== false || !preg_match('/\.(php|js)$/', $p)) {
        continue;
    }
    $all .= file_get_contents($p);
}

foreach ($suspects as $key) {
    /* 完整键直接出现（含动态拼串里的字面片段）？ */
    $direct = substr_count($all, $key);
    /* 去尾部数字的词根（EVip1Name → EVip）：拼串场景 */
    $stem = preg_replace('/\d+$/', '', $key);
    $stemHits = $stem !== $key ? substr_count($all, $stem) : 0;
    $stemDynamic = ($stem !== $key && preg_match("~['\"]" . $stem . "['\"]?\s*\.\s*\\\$~", $all)) || preg_match("~\\\$[A-Za-z]+\s*\.\s*['\"]" . $stem . "~", $all);
    printf("%-24s direct=%-4d stem=%-10s stemDynamic=%s\n", $key, $direct, $stem, $stemDynamic ? 'YES' : 'no');
}
