<?php
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/includes/auth.php';
require_once dirname(__DIR__, 2) . '/src/includes/configuration.php';
require_once dirname(__DIR__, 2) . '/src/function/integrations.php';
$n=0;
$check=static function($condition,$message)use(&$n){$n++;if(!$condition)throw new RuntimeException($message);};
foreach (['development'=>'http://localhost:8080','staging'=>'https://staging.stridebr.com.br','production'=>'https://stridebr.com.br'] as $env=>$origin) {
    putenv('STRIDEBR_APP_ENV='.$env);putenv('STRIDEBR_APP_URL='.$origin);
    $_SERVER=['REMOTE_ADDR'=>'203.0.113.9','HTTP_HOST'=>'attacker.invalid','HTTP_X_FORWARDED_PROTO'=>'https','HTTP_X_REAL_IP'=>'1.1.1.1'];
    putenv('STRIDEBR_TRUSTED_PROXIES=');
    $check(stridebr_app_env()===$env,'env');
    $check(stridebr_app_url()===$origin && stridebr_public_url()===$origin,'origin injection');
    $check(stridebr_auth_google_redirect_uri()===$origin.'/auth/google-callback.php','google callback');
    foreach(['strava','polar','fitbit','suunto','garmin'] as $provider) $check(stridebr_integrations_callback_uri($provider)===$origin.'/auth/integration-callback.php?provider='.$provider,'integration callback');
    $check(stridebr_client_ip()==='203.0.113.9','untrusted IP');
    $check(!stridebr_request_is_https(),'untrusted HTTPS');
    $check(stridebr_secure_cookie()===($env!=='development'),'cookie');
}
putenv('STRIDEBR_TRUSTED_PROXIES=10.0.0.0/8,2001:db8:ffff::/48');
$_SERVER=['REMOTE_ADDR'=>'10.0.0.2','HTTP_X_REAL_IP'=>'203.0.113.5','HTTP_X_FORWARDED_PROTO'=>'https'];
$check(stridebr_client_ip()==='203.0.113.5' && stridebr_request_is_https(),'trusted real IP and HTTPS');
$_SERVER['HTTP_X_FORWARDED_FOR']='1.1.1.1, 203.0.113.5, 10.0.0.1';
$check(stridebr_client_ip()==='203.0.113.5','right to left chain');
$_SERVER['HTTP_X_FORWARDED_FOR']='invalid, 10.0.0.1';
$check(stridebr_client_ip()==='10.0.0.2','malformed safe fallback');
$check(stridebr_ip_in_cidr('2001:db8:ffff::123','2001:db8:ffff::/48'),'IPv6');
$check(!stridebr_ip_in_cidr('2001:db8:abcd::123','2001:db8:ffff::/48'),'IPv6 outside');
putenv('STRIDEBR_APP_ENV=staging');putenv('STRIDEBR_ROBOTS_NOINDEX');
$check(stridebr_robots_noindex()&&!stridebr_is_production(),'staging noindex');
putenv('STRIDEBR_APP_ENV=production');$check(!stridebr_robots_noindex(),'production index');
putenv('STRIDEBR_DB_PASSWORD=');putenv('STRIDEBR_MAIL_TRANSPORT=disabled');
$result=stridebr_configuration_check(false);$check(!$result['ok'] && in_array('DB',$result['errors']) && in_array('MAIL',$result['errors']),'required missing');
foreach (['staging','production','development'] as $supportEnv) {
    putenv('STRIDEBR_APP_ENV='.$supportEnv);
    foreach (['', 'invalid-email', "bad@example.invalid\r\nBcc: injected@example.invalid"] as $invalidSupport) {
        putenv('STRIDEBR_SUPPORT_EMAIL='.$invalidSupport);
        $check(in_array('SUPPORT_EMAIL',stridebr_configuration_check(false)['errors']) === ($supportEnv !== 'development'),'support required in published environments');
        if ($supportEnv !== 'development') {
            try { stridebr_support_email(); $check(false,'personal fallback accepted'); }
            catch (RuntimeException) { $check(true,'personal fallback blocked'); }
        }
    }
    putenv('STRIDEBR_SUPPORT_EMAIL=support@example.invalid');
    $check(!in_array('SUPPORT_EMAIL',stridebr_configuration_check(false)['errors']) && stridebr_support_email()==='support@example.invalid','valid support accepted');
}
putenv('STRIDEBR_APP_ENV=production');
foreach(['HOST'=>'db.internal','NAME'=>'fixture','USER'=>'fixture','PASSWORD'=>'fixture-only-NOT-A-REAL-SECRET-39271'] as $key=>$value)putenv('STRIDEBR_DB_'.$key.'='.$value);
putenv('STRIDEBR_MAIL_TRANSPORT=smtp');putenv('STRIDEBR_MAIL_FROM=fixture@example.invalid');putenv('STRIDEBR_SMTP_HOST=smtp.example.invalid');putenv('STRIDEBR_SMTP_USERNAME=fixture');putenv('STRIDEBR_SMTP_PASSWORD=fixture-only-mail-secret');putenv('STRIDEBR_SMTP_ENCRYPTION=tls');putenv('GOOGLE_OAUTH_REDIRECT_URI=');
$result=stridebr_configuration_check(false);$check($result['ok'],'valid production config');
foreach (['0.0.0.0/0','0.0.0.0/00','::/0'] as $universal) {
    putenv('STRIDEBR_TRUSTED_PROXIES='.$universal);
    $check(!stridebr_configuration_check(false)['ok'] && !stridebr_trusted_proxy('192.0.2.4'),'universal proxy rejected');
}
putenv('STRIDEBR_TRUSTED_PROXIES=');
$check(!str_contains(json_encode($result),'fixture-only'),'no secret output');
putenv('STRIDEBR_SMTP_ENCRYPTION=none');$check(!stridebr_mail_is_configured(),'no plaintext SMTP production');
putenv('STRIDEBR_MAIL_TRANSPORT=mail');$check(!stridebr_mail_is_configured(),'no implicit mail production');
putenv('STRIDEBR_ADS_ENABLED=0');$check(!str_contains(stridebr_content_security_policy(),'doubleclick'),'ads origins disabled');
putenv('STRIDEBR_ADS_ENABLED=1');putenv('STRIDEBR_ADSENSE_CLIENT=ca-pub-1234567890123456');$check(str_contains(stridebr_content_security_policy(),'doubleclick'),'ads origins configured');
$check(str_contains(stridebr_content_security_policy(true),"frame-ancestors 'self'"),'embed CSP');
foreach(['STRAVA','POLAR','FITBIT','SUUNTO'] as $provider){putenv($provider.'_CLIENT_ID=');putenv($provider.'_CLIENT_SECRET=');}
foreach(stridebr_integrations_registry() as $provider)$check(!stridebr_integrations_configured($provider),'optional fails closed');
putenv('STRIDEBR_APP_ENV=staging');putenv('STRIDEBR_APP_URL=http://staging.example.invalid');
try{stridebr_app_url();$check(false,'HTTP staging accepted');}catch(RuntimeException){$check(true,'HTTP rejected');}
echo "✓ deploy configuration: $n assertions\n";
