<?php
// mc_host.php - Hébergeur Minecraft en un seul fichier PHP

// Vérifier si Docker est disponible
function checkDocker() {
    exec('docker --version', $output, $return);
    return $return === 0;
}

// Démarrer un serveur Minecraft
function startServer($version = '1.20.1', $type = 'paper', $maxPlayers = 20) {
    $containerId = uniqid('mc_');
    $port = rand(30000, 40000);
    
    $cmd = "docker run -d --name {$containerId} " .
           "-e EULA=TRUE " .
           "-e VERSION={$version} " .
           "-e TYPE={$type} " .
           "-e MAX_PLAYERS={$maxPlayers} " .
           "-p {$port}:25565 " .
           "--memory 2G " .
           "itzg/minecraft-server 2>&1";
    
    exec($cmd, $output, $return);
    
    if ($return !== 0) {
        return ['error' => implode("\n", $output)];
    }
    
    return [
        'id' => $containerId,
        'port' => $port,
        'ip' => $_SERVER['SERVER_ADDR'],
        'status' => 'starting'
    ];
}

// Arrêter un serveur
function stopServer($containerId) {
    exec("docker stop {$containerId} && docker rm {$containerId}", $output, $return);
    return $return === 0;
}

// Obtenir le statut d'un serveur
function getServerStatus($containerId) {
    exec("docker inspect -f '{{.State.Status}}' {$containerId}", $output, $return);
    return $return === 0 ? $output[0] : 'stopped';
}

// Obtenir les logs du serveur
function getServerLogs($containerId, $lines = 50) {
    exec("docker logs --tail {$lines} {$containerId} 2>&1", $output);
    return implode("\n", $output);
}

// Interface Web
if (!checkDocker()) {
    die("<h1>Erreur: Docker n'est pas installé ou ne fonctionne pas</h1>");
}

// Gestion des actions
$action = $_POST['action'] ?? '';
$serverId = $_POST['server_id'] ?? '';

if ($action === 'start') {
    $version = $_POST['version'] ?? '1.20.1';
    $type = $_POST['type'] ?? 'paper';
    $maxPlayers = $_POST['max_players'] ?? 20;
    
    $result = startServer($version, $type, $maxPlayers);
    if (isset($result['error'])) {
        $error = $result['error'];
    } else {
        $serverId = $result['id'];
        $serverPort = $result['port'];
        $serverIp = $result['ip'];
    }
} elseif ($action === 'stop' && !empty($serverId)) {
    stopServer($serverId);
    $serverId = '';
} elseif ($action === 'refresh' && !empty($serverId)) {
    // Just refresh the page
}

// Récupérer le statut actuel
$status = '';
$logs = '';
$connectionInfo = '';

if (!empty($serverId)) {
    $status = getServerStatus($serverId);
    if ($status !== 'stopped') {
        $logs = getServerLogs($serverId);
        $connectionInfo = "Connectez-vous avec: {$serverIp}:{$serverPort}";
    } else {
        $serverId = '';
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Hébergeur Minecraft PHP</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .panel { background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .console { background: #000; color: #0f0; padding: 10px; border-radius: 5px; height: 300px; overflow-y: scroll; }
        form { margin-bottom: 20px; }
        .status { font-weight: bold; }
        .online { color: green; }
        .offline { color: red; }
    </style>
</head>
<body>
    <h1>Hébergeur Minecraft</h1>
    
    <div class="panel">
        <h2>Contrôle du serveur</h2>
        
        <?php if (empty($serverId)): ?>
            <form method="post">
                <input type="hidden" name="action" value="start">
                
                <label>Version de Minecraft:
                    <select name="version">
                        <option value="1.20.1">1.20.1</option>
                        <option value="1.19.4">1.19.4</option>
                        <option value="1.18.2">1.18.2</option>
                    </select>
                </label><br><br>
                
                <label>Type de serveur:
                    <select name="type">
                        <option value="paper">Paper</option>
                        <option value="spigot">Spigot</option>
                        <option value="vanilla">Vanilla</option>
                    </select>
                </label><br><br>
                
                <label>Joueurs max: <input type="number" name="max_players" value="20" min="1" max="50"></label><br><br>
                
                <button type="submit">Démarrer le serveur</button>
            </form>
        <?php else: ?>
            <p class="status">Statut: <span class="<?= $status === 'running' ? 'online' : 'offline' ?>">
                <?= ucfirst($status) ?>
            </span></p>
            
            <?php if (!empty($connectionInfo)): ?>
                <p><?= $connectionInfo ?></p>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="action" value="stop">
                <input type="hidden" name="server_id" value="<?= htmlspecialchars($serverId) ?>">
                <button type="submit">Arrêter le serveur</button>
            </form>
            
            <form method="post">
                <input type="hidden" name="action" value="refresh">
                <input type="hidden" name="server_id" value="<?= htmlspecialchars($serverId) ?>">
                <button type="submit">Actualiser les logs</button>
            </form>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($serverId)): ?>
        <div class="panel">
            <h2>Console du serveur</h2>
            <div class="console"><?= nl2br(htmlspecialchars($logs)) ?></div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="panel" style="color: red;">
            <h2>Erreur</h2>
            <p><?= htmlspecialchars($error) ?></p>
        </div>
    <?php endif; ?>
</body>
</html>