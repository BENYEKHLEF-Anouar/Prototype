<?php
// This block assumes the following variables have been set by the parent script (sessions.php):
// $sessions, $totalSessions, $sort, $totalPages, $page, $queryString
?>
<div class="results-header" data-aos="fade-in">
    <span class="results-count"><b><?= $totalSessions ?></b> session(s) trouvée(s)</span>
    <div class="sort-control">
        <label for="sort-select">Trier par :</label>
        <select id="sort-select" class="filter-select">
            <option value="pertinence" <?= $sort === 'pertinence' ? 'selected' : '' ?>>Pertinence</option>
            <option value="date" <?= $sort === 'date' ? 'selected' : '' ?>>Date la plus proche</option>
            <option value="prix_asc" <?= $sort === 'prix_asc' ? 'selected' : '' ?>>Prix croissant</option>
            <option value="prix_desc" <?= $sort === 'prix_desc' ? 'selected' : '' ?>>Prix décroissant</option>
        </select>
    </div>
</div>

<div class="sessions-grid">
    <?php if (empty($sessions)): ?>
        <p class="no-results">Aucune session ne correspond à vos critères de recherche. Essayez de modifier ou réinitialiser vos filtres.</p>
    <?php else: ?>
        <?php foreach ($sessions as $index => $session): ?>
            <div class="session-card <?= getSessionStyleClass($session['sujet']) ?>" data-aos="fade-up" data-aos-delay="<?= ($index % 2) * 100 ?>">
                <div class="session-header">
                    <div class="session-header-icon"></div>
                    <h3 class="session-title"><?= htmlspecialchars($session['titreSession']) ?></h3>
                </div>
                <div class="session-body">
                    <div class="session-host">
                        <img src="<?= get_profile_image_path($session['mentor_photo']) ?>" alt="Photo de <?= htmlspecialchars($session['mentor_prenom']) ?>" class="host-avatar">
                        <div class="host-info">
                            <h4>Animée par <?= htmlspecialchars($session['mentor_prenom'] . ' ' . $session['mentor_nom']) ?></h4>
                            <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($session['mentor_ville'] ?? 'En ligne') ?></p>
                        </div>
                    </div>
                    <div class="session-details">
                        <div class="session-detail"><i class="far fa-clock"></i><span>Durée: <?= formatDuration($session['duree_minutes']) ?></span></div>
                        <div class="session-detail"><i class="fas fa-video"></i><span><?= ($session['typeSession'] == 'en_ligne') ? 'En ligne' : 'Présentiel' ?></span></div>
                        <div class="session-detail"><i class="fas fa-graduation-cap"></i><span><?= htmlspecialchars($session['sujet']) ?></span></div>
                        <div class="session-detail"><i class="fas fa-tag"></i><span class="session-price <?= ($session['tarifSession'] > 0) ? 'paid' : 'free' ?>"><?= ($session['tarifSession'] > 0) ? htmlspecialchars(number_format($session['tarifSession'], 0)) . ' MAD' : 'Gratuit' ?></span></div>
                    </div>
                </div>
                <div class="session-actions">
                    <a href="register_for_session.php?id=<?= $session['idSession'] ?>" class="btn btn-primary"><i class="fa-solid fa-check"></i> Réserver</a>
                    <a href="session_details.php?id=<?= $session['idSession'] ?>" class="session-details-link">Voir détails <i class="fa-solid fa-arrow-right session-details-icon"></i></a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
<nav class="pagination" aria-label="Page navigation" data-aos="fade-up">
    <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&<?= $queryString ?>" class="pagination-link prev" data-page="<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i> Précédent</a><?php endif; ?>
    <?php for ($i = 1; $i <= $totalPages; $i++): ?><a href="?page=<?= $i ?>&<?= $queryString ?>" class="pagination-link <?= ($i == $page) ? 'active' : '' ?>" data-page="<?= $i ?>"><?= $i ?></a><?php endfor; ?>
    <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&<?= $queryString ?>" class="pagination-link next" data-page="<?= $page + 1 ?>">Suivant <i class="fas fa-chevron-right"></i></a><?php endif; ?>
</nav>
<?php endif; ?>