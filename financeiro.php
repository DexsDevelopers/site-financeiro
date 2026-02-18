<?php
// financeiro.php

require_once 'templates/header.php';
?>

<style>
    .intro-card {
        background: linear-gradient(135deg, rgba(30, 30, 30, 0.5) 0%, rgba(30, 40, 50, 0.5) 100%);
    }
    .intro-card h1 {
        font-weight: 700;
    }
    .feature-icon {
        font-size: 2.5rem;
        color: var(--accent-red);
    }
    
    /* Responsividade específica para financeiro.php */
    @media (max-width: 767.98px) {
        .intro-card {
            padding: 1.5rem 1rem !important;
        }
        
        .intro-card .display-5 {
            font-size: 1.5rem !important;
            line-height: 1.3;
        }
        
        .intro-card .lead {
            font-size: 0.95rem !important;
            padding: 0;
        }
        
        .feature-icon {
            font-size: 2rem !important;
            margin-bottom: 0.75rem !important;
        }
        
        .card-custom h5 {
            font-size: 1.125rem !important;
        }
        
        .card-custom p {
            font-size: 0.875rem !important;
        }
    }
</style>

<div class="card card-custom intro-card border-0" data-aos="fade-up">
    <div class="card-body p-4 p-md-5 text-center">
        <i class="bi bi-piggy-bank-fill display-1 text-danger mb-4"></i>
        <h1 class="display-5">Controle Total da Sua Vida Financeira</h1>
        <p class="lead text-white-50 col-md-8 mx-auto">Esta é a sua central de comando para entender, planejar e otimizar seu dinheiro. Cada ferramenta abaixo foi projetada para te dar clareza e poder sobre suas finanças.</p>
    </div>
</div>

<div class="row g-4 mt-4">
    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
        <div class="card card-custom h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3"><i class="bi bi-gem"></i></div>
                <h5 class="card-title">Metas de Compras</h5>
                <p class="text-white-50">Sonhando com algo novo? Crie metas, defina valores e acompanhe seu progresso de economia para alcançar seus objetivos, um passo de cada vez.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card card-custom h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3"><i class="bi bi-graph-up"></i></div>
                <h5 class="card-title">Relatórios</h5>
                <p class="text-white-50">O conhecimento é poder. Analise seus gastos e receitas por períodos específicos, entenda seus hábitos e tome decisões mais inteligentes.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="300">
        <div class="card card-custom h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3"><i class="bi bi-card-list"></i></div>
                <h5 class="card-title">Extrato</h5>
                <p class="text-white-50">A visão completa de cada centavo que entra e sai. Edite, exclua e filtre suas transações para ter um registro detalhado e preciso.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="400">
        <div class="card card-custom h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3"><i class="bi bi-arrow-repeat"></i></div>
                <h5 class="card-title">Recorrentes</h5>
                <p class="text-white-50">Automatize sua vida financeira. Cadastre suas contas e receitas fixas (salário, aluguel, etc.) e deixe o sistema lançá-las para você todo mês.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="500">
        <div class="card card-custom h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3"><i class="bi bi-bullseye"></i></div>
                <h5 class="card-title">Orçamentos</h5>
                <p class="text-white-50">Defina limites de gastos para suas categorias (ex: R$ 500 para Alimentação) e acompanhe em tempo real se você está dentro do planejado.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="600">
        <div class="card card-custom h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3"><i class="bi bi-tags-fill"></i></div>
                <h5 class="card-title">Categorias</h5>
                <p class="text-white-50">A base da organização. Crie e gerencie suas próprias categorias de despesas e receitas para saber exatamente para onde seu dinheiro está indo.</p>
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