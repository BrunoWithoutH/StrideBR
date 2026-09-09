            <section class="trainer-section">
                <div class="section-title-row"><div><h2><?php echo stridebr_e(stridebr_t('trainer.as_athlete')); ?></h2><p><?php echo stridebr_e(stridebr_t('trainer.invite_help')); ?></p></div></div>


                <div class="trainer-people-grid">
                    <?php foreach ($asAthlete as $link): ?>
                        <article class="content-card trainer-person-card">
                            <div class="trainer-person-heading"><img src="<?php echo stridebr_e(treinadorAvatar($link)); ?>" alt="" width="48" height="48" loading="lazy" decoding="async"><div><strong><?php echo stridebr_e(stridebr_person_name_for_display((string) $link['nome_exibicao'], (string) ($link['username'] ?? ''), stridebr_t('common.user'), 60)); ?></strong><?php if ($link['username']): ?><span>@<?php echo stridebr_e($link['username']); ?></span><?php endif; ?></div><span class="status-pill"><?php echo stridebr_t($link['status'] === 'aceito' ? 'trainer.coach_active' : ($link['solicitado_por'] === 'treinador' ? 'trainer.invite_received' : 'trainer.awaiting_response')); ?></span></div>
                            <?php if ($link['status'] === 'pendente' && $link['solicitado_por'] === 'treinador'): ?>
                                <div class="trainer-card-actions">
                                    <form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="accept_link"><input type="hidden" name="idvinculo" value="<?php echo stridebr_e($link['idvinculo']); ?>"><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('friends.accept')); ?></button></form>
                                    <form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="reject_link"><input type="hidden" name="idvinculo" value="<?php echo stridebr_e($link['idvinculo']); ?>"><button type="submit" class="secondary-button"><?php echo stridebr_e(stridebr_t('friends.decline')); ?></button></form>
                                </div>
                            <?php elseif ($link['status'] === 'aceito'): ?>
                                <form method="POST" class="trainer-permissions">
                                    <?php echo stridebr_csrf_field(); ?>
                                    <input type="hidden" name="action" value="update_permissions">
                                    <input type="hidden" name="idvinculo" value="<?php echo stridebr_e($link['idvinculo']); ?>">
                                    <strong><?php echo stridebr_e(stridebr_t('trainer.permissions')); ?></strong>
                                    <p class="trainer-permission-help"><?php echo stridebr_e(stridebr_t('trainer.subtitle')); ?> <?php echo stridebr_e(stridebr_t('trainer.permissions_help')); ?></p>
                                    <label><input type="checkbox" name="pode_prescrever"<?php echo stridebr_db_bool($link['pode_prescrever']) ? ' checked' : ''; ?>> <?php echo stridebr_e(stridebr_t('trainer.prescribe')); ?></label>
                                    <label><input type="checkbox" name="pode_ver_cronograma"<?php echo stridebr_db_bool($link['pode_ver_cronograma']) ? ' checked' : ''; ?>> <?php echo stridebr_e(stridebr_t('trainer.view_schedules')); ?></label>
                                    <label><input type="checkbox" name="pode_ver_atividades"<?php echo stridebr_db_bool($link['pode_ver_atividades']) ? ' checked' : ''; ?>> <?php echo stridebr_e(stridebr_t('trainer.view_activities')); ?></label>
                                    <label><input type="checkbox" name="pode_ver_feedback"<?php echo stridebr_db_bool($link['pode_ver_feedback']) ? ' checked' : ''; ?>> <?php echo stridebr_e(stridebr_t('trainer.view_feedback')); ?></label>
                                    <button type="submit" class="secondary-button"><?php echo stridebr_e(stridebr_t('trainer.save_permissions')); ?></button>
                                </form>
                                <details class="trainer-more-menu">
                                    <summary aria-label="<?php echo stridebr_e(stridebr_t('schedule.more_actions')); ?>">•••</summary>
                                    <div><form method="POST" data-confirm="<?php echo stridebr_e(stridebr_t('trainer.end_coach_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="end_link"><input type="hidden" name="idvinculo" value="<?php echo stridebr_e($link['idvinculo']); ?>"><button type="submit" class="is-danger"><?php echo stridebr_e(stridebr_t('trainer.end_link')); ?></button></form></div>
                                </details>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    <?php if ($asAthlete === []): ?><div class="content-card trainer-empty rich"><strong><?php echo stridebr_e(stridebr_t('trainer.no_coach')); ?></strong></div><?php endif; ?>
                </div>
                <details class="trainer-section"<?php echo $asAthlete === [] ? ' open' : ''; ?>><summary><?php echo stridebr_e(stridebr_t('trainer.find')); ?></summary>
                <form method="POST" class="content-card trainer-invite-form">
                    <?php echo stridebr_csrf_field(); ?>
                    <input type="hidden" name="action" value="request_trainer">
                    <label><?php echo stridebr_e(stridebr_t('trainer.find')); ?><input type="text" name="username" maxlength="40" placeholder="@username" autocapitalize="none" spellcheck="false" required></label>
                    <button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('trainer.request_link')); ?></button>
                </form>                </details>
            </section>

            <?php if ($myPrescriptions !== []): ?>
                <section class="trainer-section">
                    <div class="section-title-row"><div><h2><?php echo stridebr_e(stridebr_t('trainer.prescribed_for_me')); ?></h2><p><?php echo stridebr_e(stridebr_t('trainer.prescribed_help')); ?></p></div></div>
                    <div class="trainer-prescription-list">
                        <?php foreach ($myPrescriptions as $item): ?>
                            <article class="content-card trainer-prescription-card">
                                <div><span><?php echo stridebr_e(stridebr_format_date_short((string) $item['data_treino'])); ?><?php echo $item['hora_inicio'] ? ' · ' . stridebr_e(substr((string) $item['hora_inicio'], 0, 5)) : ''; ?></span><h3><?php echo stridebr_e($item['titulo']); ?></h3><small><?php echo stridebr_e($item['treinador_nome'] ?? stridebr_t('trainer.coach_fallback')); ?><?php echo $item['treinador_username'] ? ' · @' . stridebr_e($item['treinador_username']) : ''; ?> · <?php echo stridebr_e(stridebr_tn('trainer.exercise_count.one','trainer.exercise_count.other',(int)$item['exercicios_total'],['count'=>(int)$item['exercicios_total']])); ?></small><?php if ($item['descricao']): ?><p><?php echo nl2br(stridebr_e($item['descricao'])); ?></p><?php endif; ?></div>
                                <div class="trainer-card-actions">
                                    <?php if ($item['status'] === 'publicado'): ?><button type="button" class="primary-button" data-start-scheduled-workout="<?php echo stridebr_e($item['idagendamento']); ?>"><?php echo stridebr_e(stridebr_t('trainer.start_workout')); ?></button><?php endif; ?>
                                    <?php if ($item['status'] === 'concluido'): ?><span class="status-pill"><?php echo stridebr_e(stridebr_t('trainer.completed')); ?></span><?php endif; ?>
                                </div>
                                <?php if ($item['status'] === 'concluido'): ?>
                                    <?php if (empty($item['feedback_em'])): ?><p><?php echo stridebr_e(stridebr_t('planning.feedback_pending')); ?></p><?php endif; ?>
                                    <form method="POST" class="trainer-feedback-form">
                                        <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="save_feedback"><input type="hidden" name="idagendamento" value="<?php echo stridebr_e($item['idagendamento']); ?>">
                                        <label><?php echo stridebr_e(stridebr_t('trainer.note')); ?><select name="nota" required><?php for ($n = 5; $n >= 1; $n--): ?><option value="<?php echo $n; ?>"<?php echo (int) ($item['nota_atleta'] ?? 0) === $n ? ' selected' : ''; ?>><?php echo $n; ?>/5</option><?php endfor; ?></select></label>
                                        <label><?php echo stridebr_e(stridebr_t('common.feedback')); ?><textarea name="feedback" maxlength="2000" rows="2" placeholder="<?php echo stridebr_e(stridebr_t('trainer.how_was_workout')); ?>"><?php echo stridebr_e($item['feedback_atleta'] ?? ''); ?></textarea></label>
                                        <button type="submit" class="secondary-button"><?php echo stridebr_e(stridebr_t('trainer.save_feedback')); ?></button>
                                    </form>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
