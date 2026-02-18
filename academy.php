<?php
// academy.php

require_once 'templates/header.php';
?>

<style>
    .intro-card {
        background: linear-gradient(135deg, rgba(30, 30, 30, 0.5) 0%, rgba(50, 30, 30, 0.5) 100%);
    }
    .intro-card h1 {
        font-weight: 700;
    }
    .feature-icon {
        font-size: 2.5rem;
        color: var(--accent-red);
    }
</style>

<div class="card card-custom intro-card border-0" data-aos="fade-up" style="cursor: pointer;">
    <a href="https://helmer-mbs.site/" target="_blank" rel="noopener" class="text-decoration-none text-white">
        <div class="card-body p-4 p-md-5 text-center">
            <i class="bi bi-rocket-takeoff-fill display-1 text-danger mb-4"></i>
            <h1 class="display-5">Bem-vindo à sua Academy</h1>
            <p class="lead text-white-50 col-md-8 mx-auto">Esta é a sua central de desenvolvimento pessoal. Clique aqui para acessar a plataforma Helmer Academy.</p>
        </div>
    </a>
</div>

<div class="row g-4 mt-4">
    <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
        <div class="card card-custom h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3"><i class="bi bi-book-half"></i></div>
                <h5 class="card-title">Meus Cursos</h5>
                <p class="text-white-50">Organize e acompanhe todos os seus cursos e estudos em um só lugar. Mova-os entre "Pendentes", "Assistindo" e "Concluídos" para visualizar seu progresso.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
        <div class="card card-custom h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3"><i class="bi bi-barbell"></i></div>
                <h5 class="card-title">Registro de Treinos</h5>
                <p class="text-white-50">Seu diário de academia digital. Anote as cargas, séries e repetições de cada exercício e acompanhe sua evolução ao longo do tempo.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
        <div class="card card-custom h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3"><i class="bi bi-clipboard2-pulse-fill"></i></div>
                <h5 class="card-title">Rotina</h5>
                <p class="text-white-50">Planeje sua semana de treinos. Defina quais grupos musculares ou atividades você fará a cada dia para criar uma rotina consistente e eficaz.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="400">
        <div class="card card-custom h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3"><i class="bi bi-egg-fried"></i></div>
                <h5 class="card-title">Alimentação</h5>
                <p class="text-white-50">Mantenha um diário das suas refeições. Registrar o que você come é o primeiro passo para uma dieta mais consciente e alinhada com seus objetivos.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 800, once: true });
});
</script>


<?php
require_once 'templates/footer.php';
?>