<?php
require_once 'api/db.php';

// Obtém o slug do produto da URL (ex: comprar.php?game=freefire)
$slug = isset($_GET['game']) ? $conn->real_escape_string($_GET['game']) : '';

if (empty($slug)) {
    // Redireciona para a home se não houver produto especificado
    header("Location: /");
    exit;
}

// Busca informações do produto
$sql_product = "SELECT * FROM products WHERE slug = '$slug' LIMIT 1";
$result_product = $conn->query($sql_product);

if ($result_product->num_rows == 0) {
    echo "Produto não encontrado.";
    exit;
}

$product = $result_product->fetch_assoc();

// Busca planos do produto
$product_id = $product['id'];
$sql_plans = "SELECT * FROM plans WHERE product_id = $product_id ORDER BY price ASC";
$result_plans = $conn->query($sql_plans);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/logo-thunder.png" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprar <?php echo htmlspecialchars($product['name']); ?> - Thunder Store</title>
    <link rel="stylesheet" crossorigin href="/assets/index-R2RkWoEQ.css">
</head>
<body class="bg-black text-white font-sans antialiased">
    <div id="root">
        <div class="min-h-screen flex flex-col bg-black text-white selection:bg-ff-red selection:text-white overflow-x-hidden">
            <!-- Navbar -->
            <nav class="fixed top-0 left-0 right-0 z-50 bg-black/80 backdrop-blur-md border-b border-white/10">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-20">
                        <a class="flex-shrink-0 flex items-center gap-2 group cursor-pointer" href="/">
                            <img src="/logo-thunder.png" alt="Thunder Store Logo" class="h-10 w-auto group-hover:scale-105 transition-transform duration-300 drop-shadow-[0_0_8px_rgba(255,0,0,0.5)]">
                            <span class="font-black text-xl tracking-wider text-white group-hover:text-ff-red transition-colors duration-300">THUNDER STORE</span>
                        </a>
                        <div class="hidden md:block">
                            <div class="ml-10 flex items-baseline space-x-8">
                                <a class="text-gray-300 hover:text-ff-red hover:scale-110 transition-all duration-300 px-3 py-2 rounded-md text-sm font-bold uppercase tracking-wide" href="/">Início</a>
                                <a class="text-gray-300 hover:text-ff-red hover:scale-110 transition-all duration-300 px-3 py-2 rounded-md text-sm font-bold uppercase tracking-wide" href="/#produtos">Produtos</a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <div class="pt-32 pb-16 px-4 sm:px-6 lg:px-8 flex-grow flex items-center justify-center">
                <div class="max-w-4xl w-full bg-[#111] border border-white/10 rounded-2xl p-8 md:p-12 shadow-[0_0_40px_rgba(255,0,0,0.1)] relative overflow-hidden">
                    <!-- Background Effects -->
                    <div class="absolute top-0 right-0 w-64 h-64 bg-ff-red/10 rounded-full blur-[100px] pointer-events-none"></div>
                    <div class="absolute bottom-0 left-0 w-64 h-64 bg-ff-red/5 rounded-full blur-[100px] pointer-events-none"></div>

                    <div class="relative z-10 grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                        <!-- Product Info -->
                        <div class="text-left">
                            <h1 class="text-4xl md:text-5xl font-black uppercase mb-4 tracking-wider"><?php echo htmlspecialchars($product['name']); ?></h1>
                            
                            <div class="inline-block bg-green-500/10 border border-green-500/20 px-4 py-1.5 rounded-full mb-6">
                                <span class="text-green-500 font-bold text-sm tracking-wide uppercase flex items-center gap-2">
                                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse shadow-[0_0_8px_#22c55e]"></span>
                                    INDETECTADO
                                </span>
                            </div>

                            <p class="text-gray-400 text-lg leading-relaxed mb-8">
                                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                            </p>

                            <!-- Features List (Optional, if you want to parse the pipe-separated features) -->
                            <?php if (!empty($product['features'])): ?>
                            <div class="flex flex-wrap gap-2 mb-8">
                                <?php 
                                $features = explode('|', $product['features']);
                                foreach ($features as $feature): 
                                ?>
                                    <span class="px-3 py-1 bg-white/5 border border-white/10 rounded-full text-xs font-bold text-gray-300 uppercase tracking-wide">
                                        <?php echo htmlspecialchars($feature); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Purchase Options -->
                        <div class="bg-[#0a0a0a] border border-white/5 rounded-xl p-6 shadow-inner">
                            <h3 class="text-xl font-bold mb-6 text-white uppercase tracking-wide border-b border-white/10 pb-4">Escolha seu Plano</h3>
                            
                            <form action="#" method="POST"> <!-- Placeholder for future payment integration -->
                                <div class="space-y-3 mb-8">
                                    <?php 
                                    $first = true;
                                    while ($plan = $result_plans->fetch_assoc()): 
                                        $checked = $first ? 'checked' : '';
                                        $first = false;
                                    ?>
                                    <label class="block relative cursor-pointer group">
                                        <input type="radio" name="plan" value="<?php echo $plan['id']; ?>" class="peer sr-only" <?php echo $checked; ?>>
                                        <div class="p-4 rounded-lg bg-[#151515] border border-white/5 peer-checked:border-ff-red peer-checked:bg-ff-red/10 transition-all duration-300 flex justify-between items-center group-hover:border-white/20">
                                            <span class="font-medium text-gray-300 peer-checked:text-white"><?php echo htmlspecialchars($plan['name']); ?></span>
                                            <span class="font-bold text-white peer-checked:text-ff-red">R$ <?php echo number_format($plan['price'], 2, ',', '.'); ?></span>
                                        </div>
                                    </label>
                                    <?php endwhile; ?>
                                </div>

                                <button type="button" onclick="alert('Sistema de pagamento em breve!')" class="w-full bg-ff-red text-white font-black uppercase py-4 rounded-lg hover:bg-red-700 transition-colors tracking-wider shadow-[0_4px_14px_rgba(255,0,0,0.4)] hover:shadow-[0_6px_20px_rgba(255,0,0,0.6)] flex items-center justify-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shopping-cart"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                                    COMPRAR AGORA
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer class="bg-black border-t border-white/10 py-12">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <p class="text-gray-500 text-sm">© 2024 Thunder Store. Todos os direitos reservados.</p>
                </div>
            </footer>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>