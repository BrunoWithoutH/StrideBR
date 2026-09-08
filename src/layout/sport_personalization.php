<?php // Shared multi-selection, using the existing sport catalog and IDs. ?>
                <div class="signup-sport-picker" data-signup-sport-picker>
                    <label class="signup-sport-search"><span><?php echo stridebr_e(stridebr_t('onboarding.search_sport')); ?></span><input type="search" placeholder="<?php echo stridebr_e(stridebr_t('onboarding.search_placeholder')); ?>" data-signup-sport-search autocomplete="off"></label>
                    <div class="signup-sport-families" data-signup-sport-families>
                        <?php foreach ($sportGroups as $group): ?>
                            <button type="button" class="signup-sport-family-card" data-signup-sport-family-open="<?php echo stridebr_e((string) $group['key']); ?>">
                                <span><strong><?php echo stridebr_e((string) $group['label']); ?></strong><small><?php echo stridebr_e((string) $group['description']); ?></small></span>
                                <b><?php echo count($group['popular']) + count($group['more']); ?></b><i aria-hidden="true">›</i>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="signup-sports-catalog" data-signup-sports-catalog>
                        <?php foreach ($sportGroups as $group): ?>
                            <section class="signup-sport-group signup-sport-family-panel" data-signup-sport-group data-signup-sport-family-panel="<?php echo stridebr_e((string) $group['key']); ?>" hidden>
                                <div class="signup-sport-family-head"><button type="button" class="context-back-button" data-signup-sport-family-back>← <?php echo stridebr_e(stridebr_t('sport_picker.categories')); ?></button><div><strong><?php echo stridebr_e((string) $group['label']); ?></strong><small><?php echo stridebr_e((string) $group['description']); ?></small></div></div>
                                <div class="signup-sport-group-title"><?php echo stridebr_e(stridebr_t('onboarding.common')); ?></div>
                                <div class="onboarding-choice-grid signup-sports-grid-main">
                                    <?php foreach ($group['popular'] as $modalidade): ?>
                                        <label class="choice-card choice-card-sport" data-signup-sport-card data-sport-name="<?php echo stridebr_e(stridebr_lower(stridebr_sport_name((string) $modalidade['slug'], (string) $modalidade['nome']) . ' ' . (string) $modalidade['nome'] . ' ' . (string) $group['label'])); ?>">
                                            <input type="checkbox" name="sports[]" value="<?php echo stridebr_e((string) $modalidade['idmodalidade']); ?>" data-summary-label="<?php echo stridebr_e(stridebr_sport_name((string) $modalidade['slug'], (string) $modalidade['nome'])); ?>"<?php echo in_array((string) $modalidade['idmodalidade'], $selectedSports, true) ? ' checked' : ''; ?>>
                                            <?php echo stridebr_sport_icon_html((string) $modalidade['slug'], 'signup-sport-icon'); ?>
                                            <span><?php echo stridebr_e(stridebr_sport_name((string) $modalidade['slug'], (string) $modalidade['nome'])); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($group['more']): ?>
                                    <button type="button" class="signup-sport-more" data-signup-sport-more aria-expanded="false"><?php echo stridebr_e(stridebr_t('onboarding.more_sports')); ?> <span aria-hidden="true">⌄</span></button>
                                    <div class="onboarding-choice-grid signup-sports-grid-main signup-sport-more-list" data-signup-sport-more-list hidden>
                                        <?php foreach ($group['more'] as $modalidade): ?>
                                            <label class="choice-card choice-card-sport" data-signup-sport-card data-sport-name="<?php echo stridebr_e(stridebr_lower(stridebr_sport_name((string) $modalidade['slug'], (string) $modalidade['nome']) . ' ' . (string) $modalidade['nome'] . ' ' . (string) $group['label'])); ?>">
                                                <input type="checkbox" name="sports[]" value="<?php echo stridebr_e((string) $modalidade['idmodalidade']); ?>" data-summary-label="<?php echo stridebr_e(stridebr_sport_name((string) $modalidade['slug'], (string) $modalidade['nome'])); ?>"<?php echo in_array((string) $modalidade['idmodalidade'], $selectedSports, true) ? ' checked' : ''; ?>>
                                                <?php echo stridebr_sport_icon_html((string) $modalidade['slug'], 'signup-sport-icon'); ?>
                                                <span><?php echo stridebr_e(stridebr_sport_name((string) $modalidade['slug'], (string) $modalidade['nome'])); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </section>
                        <?php endforeach; ?>
                        <div class="signup-sport-empty" data-signup-sport-empty hidden><?php echo stridebr_e(stridebr_t('onboarding.no_sport')); ?></div>
                    </div>
                </div>
