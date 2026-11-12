<?php
// Ensure we have tags if not already loaded (safety check for pages that might include footer directly)
if (!isset($tags_list) && isset($pdo)) {
    try {
        $tags_list = $pdo->query("SELECT id, name FROM tags ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $tags_list = [];
    }
}
?>
<!-- webhook auto-deploy test: 2026-07-18 -->
<footer class="bg-gray-900 border-t border-gray-800 pt-16 pb-8 mt-auto">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-12">
            
            <!-- Col 1: About -->
            <div class="space-y-4">
                <h3 class="text-2xl font-bold text-white"><span class="text-orange-500">il-hub</span>.com</h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                    Id-destinazzjoni numru wieħed għall-podcasts, diskussjonijiet u programmi Maltin. Aħna ngħaqqdu l-aħjar kontenut f'post wieħed għalik.
                </p>
                <div class="pt-2">
                    <a href="/suggest.php" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700 transition-colors shadow-lg shadow-orange-900/20">
                        Issuġġerixxi Programm
                    </a>
                </div>
            </div>

            <!-- Col 2: Quick Links -->
            <div>
                <h4 class="text-white font-semibold mb-4 uppercase tracking-wider text-sm border-b border-gray-800 pb-2 inline-block">Navigazzjoni</h4>
                <ul class="space-y-2">
                    <li><a href="/index.php" class="text-gray-400 hover:text-orange-400 transition-colors text-sm flex items-center"><span class="w-1.5 h-1.5 bg-gray-600 rounded-full mr-2"></span> il-hub</a></li>
                    <li><a href="/suggest.php" class="text-gray-400 hover:text-orange-400 transition-colors text-sm flex items-center"><span class="w-1.5 h-1.5 bg-gray-600 rounded-full mr-2"></span> Ikkuntattjana</a></li>
                    <li><a href="/sitemap.php" class="text-gray-400 hover:text-orange-400 transition-colors text-sm flex items-center"><span class="w-1.5 h-1.5 bg-gray-600 rounded-full mr-2"></span> XML Sitemap</a></li>
                    <li><a href="/admin.php" class="text-gray-400 hover:text-orange-400 transition-colors text-sm flex items-center"><span class="w-1.5 h-1.5 bg-gray-600 rounded-full mr-2"></span> Admin Login</a></li>
                </ul>
            </div>

            <!-- Col 3: Categories -->
            <div>
                <h4 class="text-white font-semibold mb-4 uppercase tracking-wider text-sm border-b border-gray-800 pb-2 inline-block">Kategoriji Popolari</h4>
                <ul class="grid grid-cols-2 gap-2">
                    <?php if (!empty($tags_list)): ?>
                        <?php foreach ($tags_list as $tag): ?>
                            <li>
                                <a href="<?php echo getCategoryUrl($tag['name']); ?>" class="text-gray-400 hover:text-orange-400 transition-colors text-sm capitalize flex items-center">
                                    <span class="text-gray-600 mr-2">#</span><?php echo translateTag($tag['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center">
            <p class="text-gray-500 text-xs text-center md:text-left">
                &copy; <?php echo date('Y'); ?> il-hub.com - Id-Drittijiet Kollha Riservati.
            </p>
            <p class="text-gray-600 text-xs mt-2 md:mt-0 flex items-center">
                Il-media Maltija, f'post wieħed.
            </p>
        </div>
    </div>
</footer>
</body>
</html>