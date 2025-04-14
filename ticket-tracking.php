<?php
/**
 * Sistem Pelacakan Tiket - Standalone Version
 * File ini menggabungkan frontend dan backend dalam satu file
 * sehingga tidak perlu mengakses API eksternal
 */

// Koneksi database
$host = '<server-db>';
$username = '<usr-db>';
$password = '<passwd-db>'; 
$database = '<nama-db>';

// Inisialisasi variabel
$errorMessage = '';
$trackingData = null;
$ticketId = isset($_GET['id']) ? intval($_GET['id']) : '';
$hasSearched = !empty($ticketId);

// Fungsi untuk mendapatkan data tracking tiket
function getTicketTrackingData($conn, $ticketId) {
    // Cari informasi tiket dasar dari ticket_replies
    $sql = "SELECT tr.ticketid, tr.userid, tr.timestamp as created_time,
            (SELECT body FROM ticket_replies WHERE ticketid = ? ORDER BY timestamp ASC LIMIT 1) as title,
            (SELECT cs.name FROM ticket_history th 
            LEFT JOIN custom_statuses cs ON SUBSTRING_INDEX(SUBSTRING_INDEX(th.message, 'diubah menjadi ', -1), ' ', 1) = cs.name
            WHERE th.ticketid = ? AND th.message LIKE '%status%diubah menjadi%' 
            ORDER BY th.timestamp DESC LIMIT 1) as status_name,
            u.first_name as client_first_name, 
            u.last_name as client_last_name,
            u.email as client_email
            FROM ticket_replies tr
            LEFT JOIN users u ON tr.userid = u.ID
            WHERE tr.ticketid = ?
            ORDER BY tr.timestamp ASC
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [
            'error' => true,
            'message' => 'Database error: ' . $conn->error
        ];
    }
    
    $stmt->bind_param("iii", $ticketId, $ticketId, $ticketId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return [
            'error' => true,
            'message' => 'Tiket tidak ditemukan'
        ];
    }
    
    $ticket = $result->fetch_assoc();
    
    // Validasi tiket berdasarkan jumlah balasan dalam ticket_replies
    $sql = "SELECT COUNT(*) as count FROM ticket_replies WHERE ticketid = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ticketId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    // Tiket valid jika memiliki lebih dari satu entri (tiket asli + balasan)
    $isValid = ($row['count'] > 1);
    
    // Jika belum valid, cek juga di ticket_history
    if (!$isValid) {
        $sql = "SELECT COUNT(*) as count FROM ticket_history WHERE ticketid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $ticketId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        // Tiket valid jika memiliki riwayat
        $isValid = ($row['count'] > 0);
    }
    
    if (!$isValid) {
        return [
            'error' => true,
            'message' => 'Tiket belum valid: Belum ada balasan atau aktivitas untuk tiket ini'
        ];
    }
    
    // Coba dapatkan kategori tiket
    $sql = "SELECT c.name 
            FROM ticket_categories c 
            ORDER BY c.ID ASC
            LIMIT 1";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $categoryData = $result->fetch_assoc();
        $ticket['category_name'] = $categoryData['name'];
    } else {
        $ticket['category_name'] = 'Tidak tersedia';
    }
    
    // Coba dapatkan produk
    $sql = "SELECT dp.name_product 
            FROM data_product dp 
            ORDER BY dp.id_product ASC
            LIMIT 1";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $productData = $result->fetch_assoc();
        $ticket['product_name'] = $productData['name_product'];
    } else {
        $ticket['product_name'] = 'Tidak tersedia';
    }
    
    // Dapatkan riwayat tiket
    $sql = "SELECT th.*, u.first_name, u.last_name 
            FROM ticket_history th
            LEFT JOIN users u ON th.userid = u.ID
            WHERE th.ticketid = ?
            ORDER BY th.timestamp DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ticketId);
    $stmt->execute();
    $historyResult = $stmt->get_result();
    
    $history = [];
    while ($row = $historyResult->fetch_assoc()) {
        $history[] = [
            'action' => $row['message'],
            'timestamp' => $row['timestamp'],
            'datetime' => date('Y-m-d H:i:s', $row['timestamp']),
            'user' => $row['first_name'] . ' ' . $row['last_name']
        ];
    }
    
    $ticket['history'] = $history;
    
    // Dapatkan balasan tiket
    $sql = "SELECT tr.*, u.first_name, u.last_name 
            FROM ticket_replies tr
            LEFT JOIN users u ON tr.userid = u.ID
            WHERE tr.ticketid = ?
            ORDER BY tr.timestamp ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ticketId);
    $stmt->execute();
    $repliesResult = $stmt->get_result();
    
    $replies = [];
    $firstReply = true;
    $clientId = $ticket['userid'];
    
    while ($row = $repliesResult->fetch_assoc()) {
        // Skip tiket asli (entri pertama) saat membangun array balasan
        if ($firstReply) {
            $firstReply = false;
            continue;
        }
        
        $replies[] = [
            'id' => $row['ID'],
            'body' => strip_tags($row['body']),
            'timestamp' => $row['timestamp'],
            'datetime' => date('Y-m-d H:i:s', $row['timestamp']),
            'user' => $row['first_name'] . ' ' . $row['last_name'],
            'is_client' => ($row['userid'] == $clientId)
        ];
    }
    
    $ticket['replies'] = $replies;
    
    // Dapatkan file lampiran
    $sql = "SELECT * FROM ticket_files WHERE ticketid = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ticketId);
    $stmt->execute();
    $filesResult = $stmt->get_result();
    
    $files = [];
    while ($row = $filesResult->fetch_assoc()) {
        $files[] = [
            'filename' => $row['upload_file_name'],
            'filetype' => $row['file_type'],
            'filesize' => $row['file_size'],
            'timestamp' => $row['timestamp'],
            'datetime' => date('Y-m-d H:i:s', $row['timestamp'])
        ];
    }
    
    $ticket['files'] = $files;
    
    // Susun timeline untuk tracking (gabungan dari riwayat dan balasan)
    $timeline = [];
    
    // Tambahkan riwayat tiket ke timeline
    foreach ($history as $item) {
        $timeline[] = [
            'action' => $item['action'],
            'timestamp' => $item['timestamp'],
            'datetime' => $item['datetime'],
            'user' => $item['user'],
            'type' => 'history'
        ];
    }
    
    // Tambahkan balasan tiket ke timeline
    foreach ($replies as $reply) {
        $userType = $reply['is_client'] ? 'PELAPOR' : 'OPERATOR';
        
        $timeline[] = [
            'action' => "BALASAN DARI " . $userType,
            'description' => substr($reply['body'], 0, 100) . (strlen($reply['body']) > 100 ? '...' : ''),
            'timestamp' => $reply['timestamp'],
            'datetime' => $reply['datetime'],
            'user' => $reply['user'],
            'type' => 'reply'
        ];
    }
    
    // Tambahkan penciptaan tiket ke timeline
    $timeline[] = [
        'action' => 'TIKET DIBUAT',
        'description' => 'Tiket telah dibuat oleh ' . $ticket['client_first_name'] . ' ' . $ticket['client_last_name'],
        'timestamp' => $ticket['created_time'],
        'datetime' => date('Y-m-d H:i:s', $ticket['created_time']),
        'user' => $ticket['client_first_name'] . ' ' . $ticket['client_last_name'],
        'type' => 'creation'
    ];
    
    // Urutkan timeline berdasarkan timestamp (terbaru di atas)
    usort($timeline, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    $ticket['timeline'] = $timeline;
    
    // Estimasi penyelesaian (hanya simulasi)
    $priorityMap = [
        'rendah' => '7 Days',
        'sedang' => '5 Days',
        'tinggi' => '3 Days',
        'urgent' => '1 Day'
    ];
    
    // Default prioritas adalah sedang
    $priority = 'sedang';
    
    // Coba deteksi prioritas dari isi tiket/balasan
    if (!empty($ticket['replies'])) {
        foreach ($ticket['replies'] as $reply) {
            $content = strtolower($reply['body']);
            if (strpos($content, 'prioritas tinggi') !== false) {
                $priority = 'tinggi';
                break;
            } else if (strpos($content, 'prioritas rendah') !== false) {
                $priority = 'rendah';
                break;
            } else if (strpos($content, 'prioritas urgent') !== false) {
                $priority = 'urgent';
                break;
            }
        }
    }
    
    $ticket['priority'] = $priority;
    $ticket['estimated_completion'] = $priorityMap[$priority];
    
    // Operator yang menangani tiket
    $operator = '';
    $operatorFound = false;
    
    if (!empty($ticket['replies'])) {
        foreach ($ticket['replies'] as $reply) {
            if (!$reply['is_client']) {
                $operator = $reply['user'];
                $operatorFound = true;
                break;
            }
        }
    }
    
    $ticket['operator'] = $operatorFound ? $operator : null;
    
    // Format data untuk kembalian
    return [
        'error' => false,
        'data' => [
            'ticket_id' => $ticketId,
            'title' => strip_tags($ticket['title']),
            'status' => $ticket['status_name'] ?? 'New',
            'created_at' => date('Y-m-d H:i:s', $ticket['created_time']),
            'category' => $ticket['category_name'],
            'product' => $ticket['product_name'],
            'priority' => $ticket['priority'],
            'estimated_completion' => $ticket['estimated_completion'],
            'reporter' => [
                'name' => $ticket['client_first_name'] . ' ' . $ticket['client_last_name'],
                'email' => $ticket['client_email']
            ],
            'operator' => $ticket['operator'],
            'timeline' => $ticket['timeline'],
            'files' => $ticket['files']
        ]
    ];
}

// Proses pelacakan jika ada ID tiket
if ($hasSearched) {
    // Connect ke database
    $conn = new mysqli($host, $username, $password, $database);
    
    // Cek koneksi
    if ($conn->connect_error) {
        $errorMessage = "Koneksi database gagal: " . $conn->connect_error;
    } else {
        // Ambil data tracking tiket
        $result = getTicketTrackingData($conn, $ticketId);
        
        if ($result['error']) {
            $errorMessage = $result['message'];
        } else {
            $trackingData = $result['data'];
        }
        
        // Tutup koneksi
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pelacakan Tiket - Halo Indoweb</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            light: '#60a5fa',
                            DEFAULT: '#3b82f6',
                            dark: '#2563eb',
                        },
                        new: '#3b82f6',
                        open: '#22c55e',
                        closed: '#ef4444',
                        waiting: '#f97316',
                        review: '#a855f7'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-5xl">
        <header class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Pelacakan Tiket Support</h1>
            <p class="text-gray-600 mt-2">Cek status dan riwayat tiket support Anda</p>
        </header>
        
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <form action="" method="GET" class="flex flex-col md:flex-row gap-4">
                <div class="flex-grow relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-ticket-alt text-gray-400"></i>
                    </div>
                    <input 
                        type="text" 
                        name="id" 
                        id="ticket-id" 
                        value="<?php echo htmlspecialchars($ticketId); ?>"
                        class="block w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-150 ease-in-out" 
                        placeholder="Masukkan ID Tiket"
                        required
                    >
                </div>
                <button 
                    type="submit" 
                    class="bg-primary hover:bg-primary-dark text-white font-medium py-3 px-6 rounded-lg transition duration-150 ease-in-out flex items-center justify-center md:w-auto w-full"
                >
                    <i class="fas fa-search mr-2"></i>
                    Lacak Tiket
                </button>
            </form>
            
            <?php if ($hasSearched && !empty($errorMessage)): ?>
            <div class="mt-4 bg-red-50 border-l-4 border-red-500 p-4 rounded">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700"><?php echo htmlspecialchars($errorMessage); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mt-4 text-sm text-gray-500">
                <p><i class="fas fa-info-circle mr-1"></i> Masukkan ID tiket untuk melacak status penanganan masalah Anda</p>
            </div>
        </div>
        
        <?php if ($hasSearched && $trackingData): ?>
        <!-- Hasil Pelacakan -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
            <!-- Header -->
            <div class="bg-primary text-white px-6 py-4 flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-ticket-alt text-2xl"></i>
                    <h2 class="ml-3 text-xl font-semibold">Tiket #<?php echo htmlspecialchars($trackingData['ticket_id']); ?></h2>
                </div>
                
                <?php
                // Menentukan status badge
                $status = strtolower($trackingData['status']);
                $badgeClass = 'bg-new';
                $statusIcon = 'fa-clock';
                
                if (strpos($status, 'open') !== false) {
                    $badgeClass = 'bg-open';
                    $statusIcon = 'fa-door-open';
                } else if (strpos($status, 'closed') !== false || strpos($status, 'selesai') !== false) {
                    $badgeClass = 'bg-closed';
                    $statusIcon = 'fa-check-circle';
                } else if (strpos($status, 'waiting') !== false || strpos($status, 'menunggu') !== false) {
                    $badgeClass = 'bg-waiting';
                    $statusIcon = 'fa-pause-circle';
                } else if (strpos($status, 'review') !== false) {
                    $badgeClass = 'bg-review';
                    $statusIcon = 'fa-eye';
                }
                ?>
                
                <span class="<?php echo $badgeClass; ?> text-white text-xs font-medium px-3 py-1 rounded-full flex items-center">
                    <i class="fas <?php echo $statusIcon; ?> mr-1"></i>
                    <?php echo strtoupper($trackingData['status']); ?>
                </span>
            </div>
            
            <!-- Info Blocks -->
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 divide-y md:divide-y-0 md:divide-x">
                <div class="p-4">
                    <div class="text-xs text-gray-500 mb-1">Layanan</div>
                    <div class="font-medium text-gray-800"><?php echo htmlspecialchars($trackingData['category']); ?></div>
                </div>
                
                <div class="p-4">
                    <div class="text-xs text-gray-500 mb-1">Pelapor</div>
                    <div class="font-medium text-gray-800"><?php echo htmlspecialchars($trackingData['reporter']['name']); ?></div>
                </div>
                
                <div class="p-4">
                    <div class="text-xs text-gray-500 mb-1">Operator</div>
                    <div class="font-medium text-gray-800">
                        <?php echo $trackingData['operator'] ? htmlspecialchars($trackingData['operator']) : 'Belum ditugaskan'; ?>
                    </div>
                </div>
                
                <div class="p-4">
                    <div class="text-xs text-gray-500 mb-1">Prioritas</div>
                    <div class="font-medium text-gray-800 capitalize"><?php echo htmlspecialchars($trackingData['priority']); ?></div>
                </div>
                
                <div class="p-4">
                    <div class="text-xs text-gray-500 mb-1">Estimasi Penyelesaian</div>
                    <div class="font-medium text-gray-800"><?php echo htmlspecialchars($trackingData['estimated_completion']); ?></div>
                </div>
            </div>
            
            <!-- Judul -->
            <div class="px-6 py-4 border-t border-b border-gray-200 bg-gray-50">
                <h3 class="font-medium text-gray-800"><?php echo htmlspecialchars($trackingData['title']); ?></h3>
                <div class="text-xs text-gray-500 mt-1">
                    <i class="far fa-calendar-alt mr-1"></i> Dibuat pada: <?php echo htmlspecialchars($trackingData['created_at']); ?>
                </div>
            </div>
            
            <!-- Timeline -->
            <div class="px-6 py-4">
                <h3 class="font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-history text-primary mr-2"></i>
                    Riwayat Status
                </h3>
                
                <div class="space-y-6 relative">
                    <!-- Garis vertikal timeline -->
                    <div class="absolute left-3 top-5 bottom-10 w-0.5 bg-gray-200"></div>
                    
                    <?php foreach ($trackingData['timeline'] as $index => $item): ?>
                    <?php 
                        // Menentukan warna dan ikon untuk jenis timeline
                        $dotClass = 'bg-white border-gray-300';
                        $iconClass = 'text-gray-400';
                        $iconName = 'fa-circle';
                        
                        if ($item['type'] == 'creation') {
                            $dotClass = 'bg-green-500 border-green-500';
                            $iconClass = 'text-white';
                            $iconName = 'fa-plus';
                        } else if ($item['type'] == 'history') {
                            $dotClass = 'bg-primary border-primary';
                            $iconClass = 'text-white';
                            $iconName = 'fa-arrows-rotate';
                        } else if ($item['type'] == 'reply') {
                            $dotClass = 'bg-yellow-500 border-yellow-500';
                            $iconClass = 'text-white';
                            $iconName = 'fa-comment';
                        }
                        
                        // Jika ini item terakhir, hilangkan class untuk menghapus garis vertikal setelahnya
                        $isLast = ($index == count($trackingData['timeline']) - 1);
                    ?>
                    <div class="relative pl-10">
                        <!-- Dot dengan ikon -->
                        <div class="absolute left-0 top-1.5 w-7 h-7 rounded-full border-2 <?php echo $dotClass; ?> flex items-center justify-center z-10">
                            <i class="fas <?php echo $iconName; ?> text-xs <?php echo $iconClass; ?>"></i>
                        </div>
                        
                        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
                            <div class="font-medium text-gray-800 uppercase text-sm"><?php echo htmlspecialchars($item['action']); ?></div>
                            
                            <?php if (!empty($item['description'])): ?>
                            <div class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($item['description']); ?></div>
                            <?php endif; ?>
                            
                            <div class="flex items-center mt-2 text-xs text-gray-500">
                                <span class="mr-3">
                                    <i class="far fa-clock mr-1"></i>
                                    <?php echo htmlspecialchars($item['datetime']); ?>
                                </span>
                                <?php if (!empty($item['user'])): ?>
                                <span>
                                    <i class="far fa-user mr-1"></i>
                                    <?php echo htmlspecialchars($item['user']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Detail Tiket -->
            <div class="px-6 py-4 border-t border-gray-200">
                <h3 class="font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-info-circle text-primary mr-2"></i>
                    Detail Tiket
                </h3>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <tbody class="divide-y divide-gray-200">
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap w-40 font-medium text-gray-700 bg-gray-50">ID Tiket</td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-800">#<?php echo htmlspecialchars($trackingData['ticket_id']); ?></td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap w-40 font-medium text-gray-700 bg-gray-50">Kategori</td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-800"><?php echo htmlspecialchars($trackingData['category']); ?></td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap w-40 font-medium text-gray-700 bg-gray-50">Produk</td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-800"><?php echo htmlspecialchars($trackingData['product']); ?></td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap w-40 font-medium text-gray-700 bg-gray-50">Email Pelapor</td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-800"><?php echo htmlspecialchars($trackingData['reporter']['email']); ?></td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap w-40 font-medium text-gray-700 bg-gray-50">Tanggal Dibuat</td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-800"><?php echo htmlspecialchars($trackingData['created_at']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if (!empty($trackingData['files'])): ?>
            <!-- Lampiran -->
            <div class="px-6 py-4 border-t border-gray-200">
                <h3 class="font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-paperclip text-primary mr-2"></i>
                    Lampiran
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($trackingData['files'] as $file): ?>
                    <div class="flex items-center p-3 border border-gray-200 rounded-lg">
                        <?php
                        // Menentukan ikon berdasarkan tipe file
                        $fileIcon = 'fa-file';
                        $fileExt = pathinfo($file['filename'], PATHINFO_EXTENSION);
                        
                        if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
                            $fileIcon = 'fa-file-image';
                        } else if (in_array($fileExt, ['pdf'])) {
                            $fileIcon = 'fa-file-pdf';
                        } else if (in_array($fileExt, ['doc', 'docx'])) {
                            $fileIcon = 'fa-file-word';
                        } else if (in_array($fileExt, ['xls', 'xlsx'])) {
                            $fileIcon = 'fa-file-excel';
                        } else if (in_array($fileExt, ['zip', 'rar', '7z'])) {
                            $fileIcon = 'fa-file-archive';
                        }
                        ?>
                        
                        <i class="fas <?php echo $fileIcon; ?> text-2xl text-gray-400 mr-3"></i>
                        <div class="flex-grow">
                            <div class="font-medium text-gray-800 text-sm truncate"><?php echo htmlspecialchars($file['filename']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($file['filesize']); ?> KB</div>
                        </div>
                        <div class="flex-shrink-0 text-xs text-gray-500"><?php echo date('d/m/Y', strtotime($file['datetime'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="px-6 py-3 bg-gray-50 text-right text-xs text-gray-500 border-t border-gray-200">
                Halo Indoweb - Pelacakan Tiket Support
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!$hasSearched): ?>
        <!-- Welcome Card -->
        <div class="bg-white rounded-xl shadow-md p-6 border border-blue-100">
            <div class="flex flex-col md:flex-row items-center">
                <div class="md:w-1/3 mb-4 md:mb-0 md:mr-8">
                    <img src="https://cdn-icons-png.flaticon.com/512/2038/2038898.png" alt="Ticket Tracking" class="w-64 mx-auto">
                </div>
                <div class="md:w-2/3">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Selamat Datang di Sistem Pelacakan Tiket</h2>
                    <p class="text-gray-600 mb-4">Pelacakan tiket memungkinkan Anda untuk:</p>
                    <ul class="space-y-2 text-gray-600 mb-6">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Melihat status terkini dari tiket yang Anda ajukan</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Melacak riwayat penanganan masalah</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Melihat estimasi waktu penyelesaian</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Mengakses file lampiran terkait tiket</span>
                        </li>
                    </ul>
                    <p class="text-gray-600 text-sm italic">Masukkan ID Tiket di form di atas untuk memulai pelacakan.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <footer class="text-center mt-8 text-gray-500 text-sm">
            <p>&copy; 2025 Halo Indoweb. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>
