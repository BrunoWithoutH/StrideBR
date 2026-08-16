<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__) . '/src/config/pg_config.php';
$errors=[];
$stmt=$pdo->prepare('SELECT termos_versao,privacidade_versao FROM usuarios WHERE idusuario=:id LIMIT 1');$stmt->execute([':id'=>$idUsuario]);$versions=$stmt->fetch();
$needs=($versions['termos_versao']??'')!==stridebr_terms_version()||($versions['privacidade_versao']??'')!==stridebr_privacy_version();
if($_SERVER['REQUEST_METHOD']==='POST'){
 stridebr_verify_csrf();
 if(!isset($_POST['aceite']))$errors[]='Você precisa confirmar que leu os documentos atuais.';
 if($errors===[]){$upd=$pdo->prepare('UPDATE usuarios SET termos_versao=:t,privacidade_versao=:p,termos_aceitos_em=NOW() WHERE idusuario=:id');$upd->execute([':t'=>stridebr_terms_version(),':p'=>stridebr_privacy_version(),':id'=>$idUsuario]);unset($_SESSION['LegalPending']);stridebr_flash('success','Documentos aceitos.');header('Location:/home.php');exit;}
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css'));?>"><title>Atualização legal | StrideBR</title></head><body><main class="auth-result-shell"><section class="content-card"><span class="eyebrow">StrideBR</span><h1><?php echo $needs?'Atualizamos nossos documentos':'Documentos em dia';?></h1><p><?php echo $needs?'Antes de continuar, leia as versões atuais dos Termos e da Política de Privacidade.':'Sua conta já aceitou as versões atuais.';?></p><?php foreach($errors as $e):?><div class="alert alert-danger"><?php echo stridebr_e($e);?></div><?php endforeach;?><?php if($needs):?><ul><li><a href="/pages/legal/terms.php" target="_blank" rel="noopener">Termos de Uso · <?php echo stridebr_e(stridebr_terms_version());?></a></li><li><a href="/pages/legal/privacy.php" target="_blank" rel="noopener">Política de Privacidade · <?php echo stridebr_e(stridebr_privacy_version());?></a></li></ul><form method="post" class="legal-accept-form"><?php echo stridebr_csrf_field();?><label><input type="checkbox" name="aceite" required> Li e aceito os documentos atuais.</label><button class="primary-action">Continuar</button></form><?php else:?><a class="primary-action" href="/home.php">Voltar ao StrideBR</a><?php endif;?></section></main></body></html>
