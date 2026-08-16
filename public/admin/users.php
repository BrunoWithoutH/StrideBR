<?php

declare(strict_types=1);
require_once dirname(__DIR__,2).'/src/includes/errors.php';
require_once dirname(__DIR__,2).'/src/includes/app.php';
$idUsuario=stridebr_require_login(); stridebr_require_role('admin');
require_once dirname(__DIR__,2).'/src/config/pg_config.php';
require_once dirname(__DIR__,2).'/src/includes/admin.php';
$errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    stridebr_verify_csrf();
    try {
        $action=(string)($_POST['action']??'');
        if ($action==='create_invite') {
            $raw=strtoupper(bin2hex(random_bytes(8)));
            $uses=max(1,min(50,(int)($_POST['usos_maximos']??1)));
            $days=max(1,min(90,(int)($_POST['dias']??14)));
            $stmt=$pdo->prepare("INSERT INTO convites_alpha (idconvite,codigo_hash,prefixo,usos_maximos,expira_em,criado_por) VALUES (:id,:hash,:prefixo,:usos,NOW()+(:dias||' days')::interval,:ator)");
            $stmt->execute([':id'=>stridebr_generate_id(),':hash'=>hash('sha256',$raw),':prefixo'=>substr($raw,0,8),':usos'=>$uses,':dias'=>(string)$days,':ator'=>$idUsuario]);
            stridebr_admin_audit($pdo,$idUsuario,'invite.create','convite',null,['usos_maximos'=>$uses,'dias'=>$days]);
            stridebr_flash('success','Convite criado: '.$raw.' — copie agora; o código completo não fica salvo.');
        } elseif ($action==='revoke_invite') {
            $id=trim((string)($_POST['idconvite']??''));
            $pdo->prepare('UPDATE convites_alpha SET ativo=FALSE WHERE idconvite=:id')->execute([':id'=>$id]);
            stridebr_admin_audit($pdo,$idUsuario,'invite.revoke','convite',$id);
            stridebr_flash('success','Convite revogado.');
        } else throw new InvalidArgumentException('Ação inválida.');
        header('Location:/admin/users.php'); exit;
    } catch(Throwable $e){$errors[]=$e instanceof RuntimeException||$e instanceof InvalidArgumentException?$e->getMessage():'Não foi possível executar a ação.';}
}
$q=trim((string)($_GET['q']??'')); $status=(string)($_GET['status']??''); $role=(string)($_GET['role']??'');
$sql="SELECT idusuario,COALESCE(NULLIF(nome_exibicao,''),nomeusuario) nome_exibicao,username,emailusuario,papelusuario,statususuario,verificado,email_verificado_em,ultimologin,dataregistrousuario,bloqueado_em FROM usuarios WHERE 1=1"; $params=[];
if($q!==''){$sql.=" AND (COALESCE(NULLIF(nome_exibicao,''),nomeusuario) ILIKE :q OR emailusuario ILIKE :q OR username ILIKE :q)";$params[':q']='%'.$q.'%';}
if(in_array($status,['Ativo','Desativado'],true)){$sql.=' AND statususuario=:status';$params[':status']=$status;}
if(in_array($role,['user','moderator','admin','owner'],true)){$sql.=' AND papelusuario=:role';$params[':role']=$role;}
$sql.=' ORDER BY dataregistrousuario DESC LIMIT 100'; $stmt=$pdo->prepare($sql); $stmt->execute($params); $users=$stmt->fetchAll();
$invites=$pdo->query('SELECT idconvite,prefixo,usos,usos_maximos,ativo,expira_em,criado_em FROM convites_alpha ORDER BY criado_em DESC LIMIT 20')->fetchAll(); $flashes=stridebr_take_flashes();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css'));?>"><title>Usuários | StrideBR Admin</title></head><body class="admin-body"><div class="container-fluid"><?php require dirname(__DIR__,2).'/src/layout/header.php';?><main class="main-content"><div class="admin-shell"><?php echo stridebr_admin_nav('users');?><div class="admin-heading"><div><span class="eyebrow">Administração</span><h1>Usuários</h1><p>Pesquise, edite, bloqueie e gerencie acesso sem tocar direto no banco.</p></div></div><?php foreach($flashes as $f):?><div class="alert alert-<?php echo stridebr_e($f['type']);?>"><?php echo stridebr_e($f['message']);?></div><?php endforeach;?><?php foreach($errors as $e):?><div class="alert alert-danger"><?php echo stridebr_e($e);?></div><?php endforeach;?><section class="admin-card"><form class="admin-filter" method="get"><input name="q" value="<?php echo stridebr_e($q);?>" placeholder="Nome, @username ou e-mail"><select name="status"><option value="">Todos os status</option><option value="Ativo"<?php echo $status==='Ativo'?' selected':'';?>>Ativo</option><option value="Desativado"<?php echo $status==='Desativado'?' selected':'';?>>Bloqueado</option></select><select name="role"><option value="">Todos os papéis</option><?php foreach(['user','moderator','admin','owner'] as $r):?><option value="<?php echo $r;?>"<?php echo $role===$r?' selected':'';?>><?php echo $r;?></option><?php endforeach;?></select><button class="secondary-action">Filtrar</button></form><div class="admin-table-wrap"><table><thead><tr><th>Usuário</th><th>Papel</th><th>Status</th><th>E-mail</th><th>Último login</th><th></th></tr></thead><tbody><?php foreach($users as $u):?><tr><td><strong><?php echo stridebr_e($u['nome_exibicao']);?></strong><small><?php echo $u['username']?'@'.stridebr_e($u['username']):stridebr_e($u['idusuario']);?></small></td><td><?php echo stridebr_e($u['papelusuario']);?></td><td><span class="status-pill <?php echo $u['statususuario']==='Ativo'?'is-ok':'is-blocked';?>"><?php echo stridebr_e($u['statususuario']);?></span></td><td><?php echo stridebr_e($u['emailusuario']);?><small><?php echo $u['email_verificado_em']||stridebr_db_bool($u['verificado'])?'verificado':'não verificado';?></small></td><td><?php echo stridebr_e($u['ultimologin']??'—');?></td><td><a class="secondary-action compact" href="/admin/user.php?id=<?php echo rawurlencode($u['idusuario']);?>">Gerenciar</a></td></tr><?php endforeach;?></tbody></table></div></section><section class="admin-card admin-table-card"><div class="admin-card-heading"><div><h2>Convites da alpha</h2><p>Útil se você ativar <code>registration.invite_only.enabled</code>.</p></div></div><form method="post" class="admin-inline-form"><?php echo stridebr_csrf_field();?><input type="hidden" name="action" value="create_invite"><label>Usos<input type="number" name="usos_maximos" min="1" max="50" value="1"></label><label>Validade (dias)<input type="number" name="dias" min="1" max="90" value="14"></label><button class="primary-action">Gerar convite</button></form><div class="admin-table-wrap"><table><thead><tr><th>Prefixo</th><th>Uso</th><th>Expira</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($invites as $i):?><tr><td><code><?php echo stridebr_e($i['prefixo']);?>…</code></td><td><?php echo (int)$i['usos'];?>/<?php echo (int)$i['usos_maximos'];?></td><td><?php echo stridebr_e($i['expira_em']??'sem prazo');?></td><td><?php echo stridebr_db_bool($i['ativo'])?'ativo':'revogado/usado';?></td><td><?php if(stridebr_db_bool($i['ativo'])):?><form method="post"><?php echo stridebr_csrf_field();?><input type="hidden" name="action" value="revoke_invite"><input type="hidden" name="idconvite" value="<?php echo stridebr_e($i['idconvite']);?>"><button class="danger-link">Revogar</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></section></div></main></div><?php require dirname(__DIR__,2).'/src/layout/footer.php';?></body></html>
