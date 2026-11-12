<?php
// 1. Include the database connection logic
require_once 'config_loader.php';

$message = '';

// 2. Handle form submission
if (isset($_POST['submit_suggestion'])) {
    $suggestion_text = $_POST['suggestion_text'];
    $contact_info = $_POST['contact_info']; // Can be empty

    if (empty($suggestion_text)) {
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Jekk jogħġbok daħħal suġġeriment.</div>';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO suggestions (suggestion_text, contact_info) VALUES (?, ?)");
            $stmt->execute([$suggestion_text, $contact_info]);
            $message = '<div class="bg-green-600 p-4 rounded-lg text-white">Grazzi! Is-suġġeriment tiegħek intbagħat lill-amministratur.</div>';
        } catch (PDOException $e) {
            $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Żball: Is-suġġeriment ma setax jintbagħat. ' . $e->getMessage() . '</div>';
        }
    }
}

// 3. Fetch data for the header nav
try {
    $tags_list = $pdo->query("SELECT id, name, slug FROM tags ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    // FIX: Added 'slug' to this query so the header dropdown works properly
    $series_list = $pdo->query("SELECT id, title, slug FROM series ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching tags: " . $e->getMessage());
}

// 4. Include Header
$page_title = "Issuġġerixxi Programm - il-hub.com";
include 'header_frontend.php';
?>

    <!-- Main Content -->
    <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="max-w-2xl mx-auto">
            <h1 class="text-3xl font-bold text-white mb-6">Issuġġerixxi Programm jew Episodju</h1>
            <p class="text-lg text-gray-400 mb-8">
                Taf b'xi podcast, serje, jew vidjow Malti tajjeb li qed jonqosna? Għidilna! Ibgħatilna link, l-isem, jew sempliċiment għidilna x'tixtieq tara.
            </p>

            <?php if ($message && strpos($message, 'Grazzi') !== false): ?>
                <?php echo $message; ?>
            <?php else: ?>
                <?php if ($message) echo '<div class="mb-6">' . $message . '</div>'; ?>
                <!-- Suggestion Form -->
                <form action="suggest.php" method="POST" class="bg-gray-800 p-8 rounded-lg shadow-lg space-y-6">
                    <div>
                        <label for="suggestion_text" class="block text-sm font-medium text-gray-300">Is-Suġġeriment Tiegħek (e.ż. isem il-programm jew link)</label>
                        <textarea name="suggestion_text" id="suggestion_text" rows="5" required class="mt-1 block w-full bg-gray-700 border-gray-600 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50"></textarea>
                    </div>

                    <div>
                        <label for="contact_info" class="block text-sm font-medium text-gray-300">L-Email Tiegħek (Mhux obbligatorja)</label>
                        <input type="email" name="contact_info" id="contact_info" class="mt-1 block w-full bg-gray-700 border-gray-600 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" placeholder="Biex inkunu nistgħu nirringrazzjawk!">
                        <p class="text-xs text-gray-500 mt-1">Nużawha biss biex nikkuntattjawk dwar is-suġġeriment tiegħek.</p>
                    </div>

                    <button type="submit" name="submit_suggestion" class="w-full bg-orange-500 hover:bg-orange-600 text-black font-medium py-3 px-4 rounded-lg shadow-md transition-colors text-lg">
                        Ibgħat Suġġeriment
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </main>

    <!-- 5. Include Footer -->
    <?php include 'footer_frontend.php'; ?>
