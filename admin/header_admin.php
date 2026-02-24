<?php
// /admin/header_admin.php (Versão Responsiva e Profissional)

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/db_connect.php';
$stmt = $pdo->prepare("SELECT tipo, nome_completo FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['tipo'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit();
}

$userName = $user['nome_completo'] ?? 'Admin';
$paginaAtual = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Painel de Administração - Sistema Financeiro</title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Painel de administração do sistema financeiro pessoal">
    <meta name="theme-color" content="#dc3545">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Admin Panel">
    
    <!-- Preload de recursos críticos -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" as="style">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Cores do Site Principal */
            --admin-accent: #e50914;
            --admin-accent-600: #c20811;
            --admin-accent-300: #ff4d55;
            --admin-accent-100: #ff6b73;
            --admin-accent-50: #ff8a90;
            
            /* Backgrounds do Site Principal */
            --admin-bg-900: #0d0d0f;
            --admin-bg-800: #141417;
            --admin-bg-700: #1c1c20;
            --admin-bg-600: #242428;
            --admin-bg-500: #2c2c30;
            
            /* Textos do Site Principal */
            --admin-text-100: #f5f5f1;
            --admin-text-200: #e8e8e4;
            --admin-text-300: #dbdbd7;
            --admin-text-400: #c7c7cb;
            --admin-text-500: #b3b3b7;
            
            /* Bordas do Site Principal */
            --admin-border: rgba(255,255,255,0.08);
            --admin-border-light: rgba(255,255,255,0.12);
            --admin-border-dark: rgba(255,255,255,0.04);
            
            /* Sombras do Site Principal */
            --admin-shadow-sm: 0 2px 8px rgba(0,0,0,0.15);
            --admin-shadow-md: 0 4px 16px rgba(0,0,0,0.2);
            --admin-shadow-lg: 0 8px 32px rgba(0,0,0,0.25);
            --admin-shadow-xl: 0 16px 64px rgba(0,0,0,0.3);
            --admin-shadow-accent: 0 4px 20px rgba(229,9,20,0.3);
            
            /* Layout */
            --admin-sidebar-width: 280px;
            --admin-sidebar-width-mobile: 100%;
            --admin-border-radius: 12px;
            --admin-border-radius-sm: 8px;
            --admin-border-radius-lg: 16px;
            --admin-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --admin-transition-fast: 0.15s ease;
            --admin-transition-normal: 0.2s ease;
            
            /* Variáveis de compatibilidade */
            --admin-bg-color: var(--admin-bg-900);
            --admin-sidebar-bg: linear-gradient(180deg, var(--admin-bg-800) 0%, var(--admin-bg-700) 100%);
            --admin-card-bg: rgba(28, 28, 32, 0.95);
            --admin-card-bg-hover: rgba(36, 36, 40, 0.98);
            --admin-border-color: var(--admin-border);
            --admin-accent-color: var(--admin-accent);
            --admin-accent-hover: var(--admin-accent-300);
            --admin-accent-light: rgba(229, 9, 20, 0.1);
            --admin-text-color: var(--admin-text-100);
            --admin-text-secondary: var(--admin-text-400);
            --admin-shadow: var(--admin-shadow-lg);
            --admin-shadow-hover: var(--admin-shadow-accent);
        }

        * {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--admin-bg-900);
            background-image: 
                radial-gradient(circle at 20% 20%, rgba(229, 9, 20, 0.12) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(229, 9, 20, 0.06) 0%, transparent 50%);
            background-attachment: fixed;
            color: var(--admin-text-100);
            min-height: 100vh;
            overflow-x: hidden;
            line-height: 1.6;
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
            position: relative;
            flex-direction: column;
        }

        /* === SIDEBAR RESPONSIVO === */
        .admin-sidebar {
            background: var(--admin-sidebar-bg);
            border-right: 1px solid var(--admin-border-color);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--admin-sidebar-width);
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .admin-sidebar.show {
            transform: translateX(0);
        }

        /* Overlay para mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        body.sidebar-open {
            overflow: hidden;
        }

        .admin-sidebar .offcanvas-header {
            border-bottom: 1px solid var(--admin-border-color);
            padding: 1.5rem 1rem;
        }

        .admin-sidebar .brand-title {
            color: var(--admin-accent-color);
            font-weight: 700;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            margin: 0;
        }

        .admin-sidebar .nav-link {
            color: var(--admin-text-secondary);
            padding: 0.875rem 1.5rem;
            margin: 0.25rem 0.75rem;
            border-radius: var(--admin-border-radius);
            transition: var(--admin-transition);
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .admin-sidebar .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .admin-sidebar .nav-link:hover::before {
            left: 100%;
        }

        .admin-sidebar .nav-link:hover {
            background: var(--admin-accent-light);
            color: var(--admin-accent-300);
            transform: translateX(8px);
            box-shadow: var(--admin-shadow-accent);
        }

        .admin-sidebar .nav-link.active {
            background: var(--admin-accent);
            color: white;
            font-weight: 600;
            box-shadow: var(--admin-shadow-accent);
        }

        .admin-sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 0.75rem;
        }

        /* === CONTEÚDO PRINCIPAL === */
        .admin-main-content {
            flex: 1;
            padding: 1rem;
            min-height: 100vh;
            transition: var(--admin-transition);
            width: 100%;
            margin-left: 0;
        }

        /* Tablet */
        @media (min-width: 768px) {
            .admin-main-content {
                padding: 1.5rem;
            }
        }

        /* Desktop - Sidebar fixo */
        @media (min-width: 992px) {
            .admin-wrapper {
                flex-direction: row;
                padding-left: var(--admin-sidebar-width);
            }
            
            .admin-sidebar {
                position: fixed;
                transform: translateX(0);
                width: var(--admin-sidebar-width);
            }
            
            .admin-main-content {
                margin-left: 0;
                width: calc(100% - var(--admin-sidebar-width));
                padding: 2rem;
            }
        }

        /* Desktop Grande */
        @media (min-width: 1400px) {
            .admin-main-content {
                padding: 2.5rem;
                max-width: 1600px;
                margin: 0 auto;
            }
        }

        /* === NAVBAR MOBILE === */
        .admin-navbar-mobile {
            background: var(--admin-card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--admin-border-color);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
                top: 0;
            z-index: 999;
            }

        .admin-navbar-mobile .navbar-brand {
            color: var(--admin-accent);
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .admin-navbar-mobile .navbar-toggler {
            border: 1px solid var(--admin-border-color);
            border-radius: var(--admin-border-radius);
            padding: 0.5rem;
            transition: var(--admin-transition);
        }

        .admin-navbar-mobile .navbar-toggler:hover {
            background: var(--admin-accent-light);
            border-color: var(--admin-accent);
        }

        .admin-navbar-mobile .navbar-toggler:focus {
            box-shadow: 0 0 0 0.25rem rgba(229, 9, 20, 0.25);
        }

        /* === CARDS MODERNOS === */
        .admin-card {
            background: var(--admin-card-bg);
            border: 1px solid var(--admin-border-color);
            border-radius: var(--admin-border-radius);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: var(--admin-shadow);
            transition: var(--admin-transition);
            position: relative;
            overflow: hidden;
        }

        .admin-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--admin-accent), var(--admin-accent-300));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .admin-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--admin-shadow-hover);
            border-color: var(--admin-accent);
        }

        .admin-card:hover::before {
            transform: scaleX(1);
        }

        .admin-card .card-header {
            background: transparent;
            border-bottom: 1px solid var(--admin-border-color);
            padding: 1.5rem;
        }

        .admin-card .card-body {
            padding: 1.5rem;
        }

        .admin-card h5 {
            color: var(--admin-accent);
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .admin-card h5 i {
            color: var(--admin-accent-300);
        }

        .admin-card p {
            color: var(--admin-text-secondary);
            margin: 0;
        }

        /* === BOTÕES === */
        .btn-admin {
            background: var(--admin-accent-color);
            border: none;
            border-radius: var(--admin-border-radius);
            color: white;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            transition: var(--admin-transition);
            position: relative;
            overflow: hidden;
        }

        .btn-admin::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-admin:hover::before {
            left: 100%;
        }

        .btn-admin:hover {
            background: var(--admin-accent-300);
            transform: translateY(-2px);
            box-shadow: var(--admin-shadow-accent);
        }

        .btn-admin:active {
            transform: translateY(0);
        }

        /* === TABELAS RESPONSIVAS === */
        .admin-table-container {
            background: var(--admin-card-bg);
            border-radius: var(--admin-border-radius);
            border: 1px solid var(--admin-border-color);
            overflow: hidden;
            box-shadow: var(--admin-shadow);
        }

        .admin-table {
            margin: 0;
            color: var(--admin-text-color);
        }

        .admin-table thead th {
            background: var(--admin-accent-light);
            border-bottom: 2px solid var(--admin-accent);
            color: var(--admin-accent);
            font-weight: 600;
            padding: 1rem;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
        }

        .admin-table tbody tr {
            transition: var(--admin-transition);
            border-bottom: 1px solid var(--admin-border-color);
        }

        .admin-table tbody tr:hover {
            background: var(--admin-accent-light);
            transform: scale(1.01);
        }

        .admin-table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border: none;
        }

        /* === MODAIS === */
        .admin-modal .modal-content {
            background: var(--admin-card-bg);
            border: 1px solid var(--admin-border-color);
            border-radius: var(--admin-border-radius);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        .admin-modal .modal-header {
            border-bottom: 1px solid var(--admin-border-color);
            background: var(--admin-accent-light);
        }

        .admin-modal .modal-title {
            color: var(--admin-accent);
            font-weight: 600;
        }

        .admin-modal .form-control,
        .admin-modal .form-select {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--admin-border-color);
            color: var(--admin-text-color);
            border-radius: var(--admin-border-radius);
            transition: var(--admin-transition);
        }

        .admin-modal .form-control:focus,
        .admin-modal .form-select:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--admin-accent);
            box-shadow: 0 0 0 0.25rem rgba(229, 9, 20, 0.25);
            color: var(--admin-text-color);
        }

        .admin-modal .form-control::placeholder {
            color: var(--admin-text-secondary);
        }

        /* === CARDS MOBILE === */
        #user-cards-mobile .admin-card {
            border: 1px solid var(--admin-border-color);
            border-radius: var(--admin-border-radius);
            background: var(--admin-card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            transition: var(--admin-transition);
        }

        #user-cards-mobile .admin-card:hover {
            background: var(--admin-card-bg-hover);
            border-color: var(--admin-accent);
            box-shadow: var(--admin-shadow-hover);
        }

        /* Hover shadow para cards de usuários ativos */
        .hover-shadow {
            transition: all 0.3s ease;
        }

        .hover-shadow:hover {
            transform: translateY(-4px);
            box-shadow: var(--admin-shadow-accent);
            border-color: var(--admin-accent) !important;
        }

        #user-cards-mobile .admin-card .card-body {
            padding: 1rem;
        }

        #user-cards-mobile .avatar-sm {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }

        #user-cards-mobile .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        #user-cards-mobile .btn-group-sm .btn {
            padding: 0.375rem 0.5rem;
            font-size: 0.75rem;
        }

        /* === RESPONSIVIDADE === */
        
        /* Tablet e Desktop Pequeno */
        @media (max-width: 991.98px) {
            .admin-wrapper {
                padding-left: 0 !important;
            }
            
            .admin-main-content {
                padding: 1rem;
                width: 100% !important;
            }

            .admin-sidebar {
                width: 100% !important;
            }

            .admin-card {
                margin-bottom: 1rem;
            }

            .admin-table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .admin-table {
                min-width: 600px;
            }
        }

        /* Mobile */
        @media (max-width: 767.98px) {
            .admin-main-content {
                padding: 0.5rem;
            }

            .admin-navbar-mobile {
                margin-bottom: 1rem;
            }

            .admin-card {
                margin-bottom: 0.75rem;
                border-radius: 8px;
            }

            .admin-card .card-body,
            .admin-card .card-header {
                padding: 0.75rem;
            }

            .admin-table-container {
                border-radius: 8px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .admin-table {
                min-width: 500px;
                font-size: 0.875rem;
            }

            .admin-table tbody td {
                padding: 0.5rem 0.25rem;
                font-size: 0.8rem;
                white-space: nowrap;
            }

            .admin-table thead th {
                padding: 0.5rem 0.25rem;
                font-size: 0.7rem;
                white-space: nowrap;
            }

            .btn-admin {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
                border-radius: 6px;
            }

            .btn-group .btn {
                padding: 0.375rem 0.5rem;
                font-size: 0.75rem;
            }

            .btn-group-sm .btn {
                padding: 0.25rem 0.375rem;
                font-size: 0.7rem;
            }

            /* Estatísticas em mobile */
            .row.g-3 {
                margin: 0 -0.25rem;
            }

            .row.g-3 > * {
                padding: 0 0.25rem;
                margin-bottom: 0.5rem;
            }

            .admin-card .fs-2 {
                font-size: 1.5rem !important;
            }

            .admin-card .h5 {
                font-size: 1rem !important;
            }

            /* Header responsivo */
            .d-flex.flex-column.flex-md-row {
                flex-direction: column !important;
                gap: 1rem;
            }

            .d-flex.flex-column.flex-md-row > div:first-child {
                text-align: center;
            }

            .d-flex.flex-column.flex-md-row .btn-admin {
                width: 100%;
                justify-content: center;
            }

            /* Cards mobile responsivos */
            #user-cards-mobile .admin-card .card-body {
                padding: 0.75rem;
            }

            #user-cards-mobile .avatar-sm {
                width: 35px;
                height: 35px;
                font-size: 0.9rem;
            }

            #user-cards-mobile .badge {
                font-size: 0.7rem;
                padding: 0.2rem 0.4rem;
            }

            #user-cards-mobile .btn-group-sm .btn {
                padding: 0.3rem 0.4rem;
                font-size: 0.7rem;
            }
        }

        /* Mobile Pequeno */
        @media (max-width: 575.98px) {
            .admin-main-content {
                padding: 0.25rem;
            }

            .admin-card .card-body,
            .admin-card .card-header {
                padding: 0.5rem;
            }

            .admin-table tbody td {
                padding: 0.375rem 0.125rem;
                font-size: 0.75rem;
            }

            .admin-table thead th {
                padding: 0.375rem 0.125rem;
                font-size: 0.65rem;
            }

            .btn-admin {
                padding: 0.375rem 0.5rem;
                font-size: 0.75rem;
            }

            .btn-group .btn {
                padding: 0.25rem 0.375rem;
                font-size: 0.7rem;
            }

            .btn-group-sm .btn {
                padding: 0.2rem 0.25rem;
                font-size: 0.65rem;
            }

            /* Avatar menor */
            .avatar-sm {
                width: 24px;
                height: 24px;
                font-size: 0.7rem;
            }

            /* Badge menor */
            .badge {
                font-size: 0.6rem;
                padding: 0.2rem 0.3rem;
            }

            /* Estatísticas ainda menores */
            .admin-card .fs-2 {
                font-size: 1.25rem !important;
            }

            .admin-card .h5 {
                font-size: 0.9rem !important;
            }

            .admin-card small {
                font-size: 0.7rem;
            }

            /* Cards mobile para telas pequenas */
            #user-cards-mobile .admin-card .card-body {
                padding: 0.5rem;
            }

            #user-cards-mobile .avatar-sm {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }

            #user-cards-mobile .badge {
                font-size: 0.65rem;
                padding: 0.15rem 0.3rem;
            }

            #user-cards-mobile .btn-group-sm .btn {
                padding: 0.25rem 0.3rem;
                font-size: 0.65rem;
            }
        }

        /* Mobile Extra Pequeno */
        @media (max-width: 400px) {
            .admin-main-content {
                padding: 0.125rem;
            }

            .admin-card .card-body,
            .admin-card .card-header {
                padding: 0.375rem;
            }

            .admin-table {
                min-width: 400px;
                font-size: 0.75rem;
            }

            .admin-table tbody td {
                padding: 0.25rem 0.1rem;
                font-size: 0.7rem;
            }

            .admin-table thead th {
                padding: 0.25rem 0.1rem;
                font-size: 0.6rem;
            }

            .btn-admin {
                padding: 0.25rem 0.375rem;
                font-size: 0.7rem;
            }

            .btn-group .btn {
                padding: 0.2rem 0.25rem;
                font-size: 0.65rem;
            }

            .btn-group-sm .btn {
                padding: 0.15rem 0.2rem;
                font-size: 0.6rem;
            }

            /* Ocultar elementos desnecessários em telas muito pequenas */
            .d-none.d-sm-inline {
                display: none !important;
            }

            .d-none.d-md-inline {
                display: none !important;
            }

            .d-none.d-lg-inline {
                display: none !important;
            }

            /* Cards mobile para telas extra pequenas */
            #user-cards-mobile .admin-card .card-body {
                padding: 0.375rem;
            }

            #user-cards-mobile .avatar-sm {
                width: 25px;
                height: 25px;
                font-size: 0.7rem;
            }

            #user-cards-mobile .badge {
                font-size: 0.6rem;
                padding: 0.1rem 0.25rem;
            }

            #user-cards-mobile .btn-group-sm .btn {
                padding: 0.2rem 0.25rem;
                font-size: 0.6rem;
            }

            #user-cards-mobile .row.g-2 {
                margin: 0 -0.125rem;
            }

            #user-cards-mobile .row.g-2 > * {
                padding: 0 0.125rem;
            }
        }

        /* === ANIMAÇÕES === */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .admin-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .admin-card:nth-child(1) { animation-delay: 0.1s; }
        .admin-card:nth-child(2) { animation-delay: 0.2s; }
        .admin-card:nth-child(3) { animation-delay: 0.3s; }
        .admin-card:nth-child(4) { animation-delay: 0.4s; }

        /* === ESTADOS DE CARREGAMENTO === */
        .loading {
            position: relative;
            overflow: hidden;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        /* === TOAST NOTIFICATIONS === */
        .admin-toast {
            background: var(--admin-card-bg);
            border: 1px solid var(--admin-border-color);
            border-radius: var(--admin-border-radius);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: var(--admin-shadow);
        }

        .admin-toast .toast-header {
            background: rgba(220, 53, 69, 0.1);
            border-bottom: 1px solid var(--admin-border-color);
            color: var(--admin-text-color);
        }

        .admin-toast .toast-body {
            color: var(--admin-text-color);
        }

        /* === DARK MODE SUPPORT === */
        @media (prefers-color-scheme: light) {
            :root {
                --admin-bg-color: #f8f9fa;
                --admin-sidebar-bg: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
                --admin-card-bg: rgba(255, 255, 255, 0.95);
                --admin-card-bg-hover: rgba(248, 249, 250, 0.98);
                --admin-border-color: rgba(0, 0, 0, 0.1);
                --admin-text-color: #212529;
                --admin-text-secondary: #6c757d;
            }
        }

        /* === ACESSIBILIDADE === */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* === FOCUS STATES === */
        .admin-sidebar .nav-link:focus,
        .btn-admin:focus,
        .admin-modal .form-control:focus,
        .admin-modal .form-select:focus {
            outline: 2px solid var(--admin-accent);
            outline-offset: 2px;
        }

        /* === FILTROS MODERNOS === */
        .btn-check:checked + .btn-outline-primary {
            background: var(--admin-accent);
            border-color: var(--admin-accent);
            color: white;
            box-shadow: var(--admin-shadow-accent);
        }

        .btn-check:checked + .btn-outline-success {
            background: #00b894;
            border-color: #00b894;
            color: white;
            box-shadow: 0 4px 20px rgba(0, 184, 148, 0.3);
        }

        .btn-check:checked + .btn-outline-info {
            background: #0984e3;
            border-color: #0984e3;
            color: white;
            box-shadow: 0 4px 20px rgba(9, 132, 227, 0.3);
        }

        .btn-check:checked + .btn-outline-secondary {
            background: var(--admin-bg-600);
            border-color: var(--admin-bg-600);
            color: white;
        }

        .btn-check:checked + .btn-outline-warning {
            background: #f9a826;
            border-color: #f9a826;
            color: white;
            box-shadow: 0 4px 20px rgba(249, 168, 38, 0.3);
        }

        .btn-group .btn {
            transition: var(--admin-transition);
        }

        .btn-group .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--admin-shadow-sm);
        }

        /* === CARDS DE ESTATÍSTICAS === */
        .admin-card.text-center {
            transition: var(--admin-transition);
            cursor: pointer;
        }

        .admin-card.text-center:hover {
            transform: translateY(-4px);
            box-shadow: var(--admin-shadow-accent);
            border-color: var(--admin-accent);
        }

        .admin-card.text-center .fs-2 {
            color: var(--admin-accent);
            transition: var(--admin-transition);
        }

        .admin-card.text-center:hover .fs-2 {
            transform: scale(1.1);
        }

        /* === INPUT GROUP MODERNO === */
        .input-group-text {
            background: var(--admin-bg-700);
            border: 1px solid var(--admin-border);
            color: var(--admin-text-300);
        }

        .form-control {
            background: var(--admin-bg-700);
            border: 1px solid var(--admin-border);
            color: var(--admin-text-100);
            transition: var(--admin-transition);
        }

        .form-control:focus {
            background: var(--admin-bg-600);
            border-color: var(--admin-accent);
            box-shadow: 0 0 0 0.25rem rgba(229, 9, 20, 0.25);
            color: var(--admin-text-100);
        }

        .form-control::placeholder {
            color: var(--admin-text-500);
        }

        /* === BADGES MODERNOS === */
        .badge {
            font-weight: 500;
            padding: 0.35em 0.65em;
            border-radius: var(--admin-border-radius-sm);
        }

        .badge.bg-success {
            background: #00b894 !important;
        }

        .badge.bg-info {
            background: #0984e3 !important;
        }

        .badge.bg-warning {
            background: #f9a826 !important;
            color: #000;
        }

        /* === ALERTAS MODERNOS === */
        .alert {
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-border-radius);
            backdrop-filter: blur(10px);
        }

        .alert-info {
            background: rgba(9, 132, 227, 0.1);
            border-color: rgba(9, 132, 227, 0.3);
            color: var(--admin-text-200);
        }

        /* === RESPONSIVIDADE MELHORADA PARA FILTROS === */
        @media (max-width: 767.98px) {
            .btn-group.flex-wrap {
                flex-direction: column;
                gap: 0.5rem;
            }

            .btn-group.flex-wrap .btn {
                width: 100%;
                margin: 0;
                border-radius: var(--admin-border-radius) !important;
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }

            .col-12.col-md-8,
            .col-12.col-md-4 {
                margin-bottom: 1rem;
            }

            .form-label {
                font-size: 0.9rem;
            }
        }

        /* === CARDS DE ESTATÍSTICAS RESPONSIVOS === */
        @media (max-width: 575.98px) {
            .row.g-3 > [class*="col-"] {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            .admin-card.text-center .card-body {
                padding: 1rem 0.75rem;
            }

            .admin-card.text-center .fs-2 {
                font-size: 1.75rem !important;
            }

            .admin-card.text-center .h5 {
                font-size: 0.9rem !important;
            }

            .admin-card.text-center small {
                font-size: 0.75rem;
            }
        }

        /* === ANIMAÇÕES SUAVES === */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .admin-card {
            animation: fadeInUp 0.4s ease-out;
        }

        .admin-card:nth-child(1) { animation-delay: 0.05s; }
        .admin-card:nth-child(2) { animation-delay: 0.1s; }
        .admin-card:nth-child(3) { animation-delay: 0.15s; }
        .admin-card:nth-child(4) { animation-delay: 0.2s; }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <!-- Overlay para mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar -->
    <aside class="admin-sidebar d-flex flex-column" id="adminSidebar">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title brand-title">
                <i class="bi bi-shield-lock-fill me-2"></i>
                Painel Admin
            </h5>
            <button type="button" class="btn-close btn-close-white" id="closeSidebar" aria-label="Fechar menu">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <div class="offcanvas-body d-flex flex-column">
            <!-- Menu Principal -->
            <ul class="nav flex-column mb-4">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($paginaAtual == 'index.php') ? 'active' : ''; ?>" href="index.php">
                        <i class="bi bi-people-fill"></i>
                        Gerenciar Usuários
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($paginaAtual == 'admin_empresas.php') ? 'active' : ''; ?>" href="admin_empresas.php">
                         <i class="bi bi-building-fill"></i>
                         Gerenciar Empresas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($paginaAtual == 'notificacoes.php') ? 'active' : ''; ?>" href="notificacoes.php">
                        <i class="bi bi-send-fill"></i>
                        Enviar Notificações
                    </a>
                </li>
            </ul>

            <!-- Informações do Admin -->
            <div class="mt-auto">
                <hr class="my-3" style="border-color: var(--admin-border-color);">
                <div class="px-3 py-2">
                    <small class="text-muted d-block">Logado como:</small>
                    <strong class="text-white"><?php echo htmlspecialchars($userName); ?></strong>
                </div>
                
                <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="../dashboard.php">
                            <i class="bi bi-arrow-left-circle-fill"></i>
                            Voltar ao Site
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">
                            <i class="bi bi-box-arrow-right"></i>
                            Sair
                    </a>
                </li>
            </ul>
            </div>
        </div>
    </aside>

    <!-- Conteúdo Principal -->
    <div class="admin-main-content">
        <!-- Navbar Mobile -->
        <nav class="navbar d-lg-none admin-navbar-mobile mb-4">
            <div class="container-fluid">
                <span class="navbar-brand">
                    <i class="bi bi-shield-lock-fill me-2"></i>
                    Painel Admin
                </span>
                <button class="navbar-toggler" type="button" id="toggleSidebar" aria-label="Abrir menu">
                    <i class="bi bi-list text-white"></i>
                </button>
            </div>
        </nav>
