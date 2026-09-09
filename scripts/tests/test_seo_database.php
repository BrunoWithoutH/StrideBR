<?php
return function (PDO $pdo): void {
    $user = alphaTestUser($pdo, 'seo_public');
    $urls = [];
    foreach (['publicado','cancelado','rascunho','encerrado'] as $status) {
        $id = eventosSalvar($pdo, $user, ['titulo'=>'Alpha test event SEO '.$status,'tipo'=>'Corrida de rua','descricao'=>'Descrição pública sintética.','data_inicio'=>'2027-05-12T08:00','cidade'=>'Cidade Teste','estado'=>'RS','pais'=>'Brasil','status'=>$status,'destaque'=>'1']);
        $stmt = $pdo->prepare('SELECT slug FROM eventos_esportivos WHERE idevento=:id'); $stmt->execute([':id'=>$id]);
        $urls[$status] = stridebr_seo_canonical('/evento.php', $stmt->fetchColumn());
    }
    $sitemap = stridebr_seo_sitemap_urls($pdo);
    AlphaTest::assert(in_array($urls['publicado'], $sitemap, true), 'Published event in sitemap');
    AlphaTest::assert(in_array($urls['cancelado'], $sitemap, true), 'Public canceled event retains its page');
    AlphaTest::assert(!in_array($urls['rascunho'], $sitemap, true), 'Draft excluded');
    AlphaTest::assert(!in_array($urls['encerrado'], $sitemap, true), 'Nonpublic closed event excluded');
    foreach ($sitemap as $url) AlphaTest::assert(!preg_match('#/(user|admin|auth|api|function|u)/#', $url), 'No personal data URLs');
};
