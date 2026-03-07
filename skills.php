<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$page_title = "Galeria de Skills - Orion";
require_once 'templates/header.php';

$skillsFile = 'includes/skills_index.json';
$skills = [];
if (file_exists($skillsFile)) {
    $skills = json_decode(file_get_contents($skillsFile), true);
}

// Agrupar por categoria
$categories = [];
foreach ($skills as $skill) {
    preg_match_all('/([A-Z][a-z]+)/', $skill['category'], $catParts);
    $cat = !empty($catParts[0]) ? implode(' ', $catParts[0]) : $skill['category'];
    $categories[$cat][] = $skill;
}
ksort($categories);
?>

<div class="container-fluid py-4" style="background: #0a0a0a; min-height: 100vh; color: white;">
    <div class="row mb-5">
        <div class="col-12 text-center">
            <h1 class="display-4 fw-bold" style="background: linear-gradient(45deg, #fff, #e50914); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Minha Biblioteca de Skills</h1>
            <p class="text-white-50">Explorar as <?= count($skills) ?> habilidades integradas ao seu ecossistema</p>
            
            <div class="mx-auto" style="max-width: 600px;">
                <div class="input-group input-group-lg shadow-lg">
                    <span class="input-group-text bg-dark border-secondary text-white-50"><i class="bi bi-search"></i></span>
                    <input type="text" id="skillSearch" class="form-control bg-dark border-secondary text-white" placeholder="Pesquisar por nome ou descrição...">
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="card bg-dark border-secondary sticky-top" style="top: 20px;">
                <div class="card-body">
                    <h5 class="card-title mb-3 text-danger"><i class="bi bi-filter"></i> Categorias</h5>
                    <div class="list-group list-group-flush bg-transparent">
                        <a href="#" class="list-group-item list-group-item-action bg-transparent text-white border-secondary active" onclick="filterCategory('all')">Todas as Categorias</a>
                        <?php foreach (array_keys($categories) as $cat): ?>
                            <a href="#" class="list-group-item list-group-item-action bg-transparent text-white-50 border-secondary" onclick="filterCategory('<?= str_replace("'", "\'", $cat) ?>')"><?= $cat ?> <span class="badge bg-danger rounded-pill float-end"><?= count($categories[$cat]) ?></span></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="row g-4" id="skillsGrid">
                <?php foreach ($skills as $skill): ?>
                <div class="col-lg-4 col-md-6 skill-card-container" data-name="<?= strtolower($skill['name']) ?>" data-desc="<?= strtolower($skill['description']) ?>" data-cat="<?= $skill['category'] ?>">
                    <div class="card h-100 bg-card border-secondary skill-card" style="transition: all 0.3s; background: rgba(30, 30, 30, 0.6); backdrop-filter: blur(10px);">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="badge bg-danger text-uppercase" style="font-size: 0.65rem;"><?= $skill['category'] ?></span>
                                <i class="bi bi-cpu text-white-50"></i>
                            </div>
                            <h5 class="card-title text-white mb-2"><?= htmlspecialchars($skill['name']) ?></h5>
                            <p class="card-text text-white-50" style="font-size: 0.85rem; height: 3.6em; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">
                                <?= htmlspecialchars($skill['description']) ?>
                            </p>
                        </div>
                        <div class="card-footer bg-transparent border-top-0 pb-4 px-4">
                            <a href="<?= $skill['path'] ?>" target="_blank" class="btn btn-outline-danger btn-sm w-100">
                                Ver Detalhes <i class="bi bi-arrow-right-short"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-card { background: #141414; }
    .skill-card:hover {
        transform: translateY(-5px);
        border-color: #e50914 !important;
        box-shadow: 0 0 20px rgba(229, 9, 20, 0.3);
        background: rgba(40, 40, 40, 0.8) !important;
    }
    .skill-card-container.hidden { display: none; }
    .list-group-item.active { background-color: #e50914 !important; border-color: #e50914 !important; color: white !important; }
    #skillSearch:focus { border-color: #e50914; box-shadow: 0 0 10px rgba(229, 9, 20, 0.4); }
</style>

<script>
document.getElementById('skillSearch').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    const cards = document.querySelectorAll('.skill-card-container');
    
    cards.forEach(card => {
        const name = card.getAttribute('data-name');
        const desc = card.getAttribute('data-desc');
        if (name.includes(term) || desc.includes(term)) {
            card.classList.remove('hidden');
        } else {
            card.classList.add('hidden');
        }
    });
});

function filterCategory(cat) {
    const cards = document.querySelectorAll('.skill-card-container');
    const links = document.querySelectorAll('.list-group-item');
    
    links.forEach(l => l.classList.remove('active'));
    event.currentTarget.classList.add('active');
    
    cards.forEach(card => {
        if (cat === 'all' || card.getAttribute('data-cat') === cat) {
            card.classList.remove('hidden');
        } else {
            card.classList.add('hidden');
        }
    });
}
</script>

<?php require_once 'templates/footer.php'; ?>
