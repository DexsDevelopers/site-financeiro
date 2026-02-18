<?php
// produtividade.php

require_once 'templates/header.php';
?>

<style>
    .intro-card {
        background: linear-gradient(135deg, rgba(30, 30, 30, 0.5) 0%, rgba(30, 50, 40, 0.5) 100%);
    }
    .intro-card h1 {
        font-weight: 700;
    }
    .feature-icon {
        font-size: 2.5rem;
        color: var(--accent-red);
    }
</style>

<div class="card card-custom intro-card border-0" data-aos="fade-up">
    <div class="card-body p-4 p-md-5 text-center">
        <i class="bi bi-speedometer2 display-1 text-danger mb-4"></i>
        <h1 class="display-5">Maximize sua Produtividade</h1>
        <p class="lead text-white-50 col-md-8 mx-auto">O sucesso financeiro e pessoal está diretamente ligado à sua capacidade de gerenciar seu tempo e suas prioridades. As ferramentas abaixo foram criadas para te ajudar a manter o foco, organizar suas ideias e executar seus planos com eficiência.</p>
    </div>
</div>

<div class="row g-4 mt-4">
    <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
        <div class="card card-custom h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3"><i class="bi bi-check2-square"></i></div>
                <h5 class="card-title">Rotina de Tarefas</h5>
                <p class="text-white-50">O cérebro do seu dia a dia. Liste tudo o que você precisa fazer, defina prioridades, quebre grandes tarefas em subtarefas menores e arraste-as para organizar sua ordem de execução.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card card-custom h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3"><i class="bi bi-calendar-week-fill"></i></div>
                <h5 class="card-title">Calendário</h5>
                <p class="text-white-50">Visualize seus compromissos e prazos de forma clara. Arraste e solte tarefas para reagendá-las e tenha uma visão macro da sua semana e do seu mês.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
        <div class="card card-custom h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3"><i class="bi bi-clock-history"></i></div>
                <h5 class="card-title">Temporizador Pomodoro</h5>
                <p class="text-white-50">Uma técnica poderosa para manter o foco total. Trabalhe em blocos de tempo concentrado, com pausas programadas, para evitar a exaustão e maximizar sua performance.</p>
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