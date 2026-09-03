<?php
$activityRouteAllowed = !empty($activityRouteAllowed);
$activityRouteValue = isset($activityRouteValue) ? (string) $activityRouteValue : '';
$activityRouteHasPoints = $activityRouteValue !== '';
$activityRouteCompact = !empty($activityRouteCompact);
?>
<section class="activity-route-builder<?php echo $activityRouteHasPoints ? ' has-route' : ''; ?>" data-route-editor data-route-allowed="<?php echo $activityRouteAllowed ? '1' : '0'; ?>" data-route-has-points="<?php echo $activityRouteHasPoints ? '1' : '0'; ?>" data-route-compact="<?php echo $activityRouteCompact ? '1' : '0'; ?>"<?php echo $activityRouteAllowed ? '' : ' hidden'; ?>>
    <input type="hidden" name="rota_coordenadas" value="<?php echo stridebr_e($activityRouteValue); ?>" data-route-value>
    <div class="activity-route-heading">
        <?php if ($activityRouteCompact): ?>
            <div class="activity-route-copy">
                <strong>Rota</strong>
                <span data-route-distance><?php echo $activityRouteHasPoints ? 'Calculando distância…' : 'Adicionar um percurso manualmente.'; ?></span>
            </div>
            <button type="button" class="activity-secondary-button activity-inline-action activity-row-action" data-route-toggle><?php echo $activityRouteHasPoints ? 'Editar' : '+ Adicionar'; ?></button>
        <?php else: ?>
            <div><span class="activity-section-label">Rota</span><strong data-route-distance><?php echo $activityRouteHasPoints ? 'Calculando distância…' : 'Nenhuma rota desenhada'; ?></strong></div>
            <button type="button" class="activity-secondary-button" data-route-toggle><?php echo $activityRouteHasPoints ? 'Editar rota' : 'Adicionar rota'; ?></button>
        <?php endif; ?>
    </div>
    <div class="activity-route-workspace" data-route-workspace hidden>
        <div class="activity-route-toolbar">
            <button type="button" data-route-locate>Minha localização</button>
            <button type="button" data-route-undo>Desfazer</button>
            <button type="button" data-route-clear>Limpar</button>
            <button type="button" class="activity-route-done" data-route-done>Rota concluída</button>
        </div>
        <div class="activity-route-map" data-route-map aria-label="Mapa para desenhar a rota"></div>
        <div class="activity-route-status"><span data-route-points>Toque no mapa para adicionar pontos.</span><span data-route-elevation>Elevação estimada após desenhar.</span></div>
        <small class="activity-route-attribution">Mapa © OpenStreetMap contributors · elevação estimada: Open-Meteo, dados Copernicus DEM.</small>
    </div>
</section>
