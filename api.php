<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'routeros_api.class.php';

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($action) {
        case 'getUsers':
            $api = new RouterosAPI();
            if ($api->connect($input['ip'], $input['username'], $input['password'], ($input['port'] ?? 8728))) {
                // For PPPoE users
                $api->write('/ppp/secret/print');
                $pppUsers = $api->read();
                
                // For Hotspot users
                $api->write('/ip/hotspot/user/print');
                $hotspotUsers = $api->read();
                
                $api->disconnect();
                
                $users = [];
                
                // Process PPPoE users
                foreach ($pppUsers as $user) {
                    $users[] = [
                        'name' => $user['name'],
                        'profile' => $user['profile'] ?? 'N/A',
                        'status' => isset($user['disabled']) && $user['disabled'] === 'true' ? 'disabled' : 'active',
                        'expires' => $user['limit-uptime'] ?? null,
                        'comment' => $user['comment'] ?? ''
                    ];
                }
                
                // Process Hotspot users
                foreach ($hotspotUsers as $user) {
                    $users[] = [
                        'name' => $user['name'],
                        'profile' => $user['profile'] ?? 'N/A',
                        'status' => isset($user['disabled']) && $user['disabled'] === 'true' ? 'disabled' : 'active',
                        'expires' => $user['limit-uptime'] ?? null,
                        'comment' => $user['comment'] ?? ''
                    ];
                }
                
                echo json_encode(['success' => true, 'users' => $users]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to connect to RouterOS']);
            }
            break;
            
        case 'getPackages':
            $api = new RouterosAPI();
            if ($api->connect($input['ip'], $input['username'], $input['password'], ($input['port'] ?? 8728))) {
                $api->write('/ppp/profile/print');
                $pppProfiles = $api->read();
                
                $api->write('/ip/hotspot/user/profile/print');
                $hotspotProfiles = $api->read();
                
                $api->disconnect();
                
                $packages = [];
                
                // Process PPPoE profiles
                foreach ($pppProfiles as $profile) {
                    $packages[] = [
                        'name' => $profile['name'],
                        'rate-limit' => $profile['rate-limit'] ?? 'N/A'
                    ];
                }
                
                // Process Hotspot profiles
                foreach ($hotspotProfiles as $profile) {
                    $packages[] = [
                        'name' => $profile['name'],
                        'rate-limit' => $profile['rate-limit'] ?? 'N/A'
                    ];
                }
                
                echo json_encode(['success' => true, 'packages' => $packages]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to connect to RouterOS']);
            }
            break;
            
        case 'addUser':
            $api = new RouterosAPI();
            if ($api->connect($input['config']['ip'], $input['config']['username'], $input['config']['password'], ($input['config']['port'] ?? 8728))) {
                // Determine if we're adding PPPoE or Hotspot user based on profile
                $api->write('/ppp/profile/print', ['?name=' . $input['profile']]);
                $isPppProfile = count($api->read()) > 0;
                
                if ($isPppProfile) {
                    // Add PPPoE user
                    $api->write('/ppp/secret/add', [
                        'name' => $input['username'],
                        'password' => $input['password'],
                        'profile' => $input['profile'],
                        'comment' => $input['comment'] ?? '',
                        'limit-uptime' => $input['validity'] . 'd'
                    ]);
                } else {
                    // Add Hotspot user
                    $api->write('/ip/hotspot/user/add', [
                        'name' => $input['username'],
                        'password' => $input['password'],
                        'profile' => $input['profile'],
                        'comment' => $input['comment'] ?? '',
                        'limit-uptime' => $input['validity'] . 'd'
                    ]);
                }
                
                $api->disconnect();
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to connect to RouterOS']);
            }
            break;
            
        case 'rechargeUser':
            $api = new RouterosAPI();
            if ($api->connect($input['config']['ip'], $input['config']['username'], $input['config']['password'], ($input['config']['port'] ?? 8728))) {
                // Try to find user in PPPoE first
                $api->write('/ppp/secret/print', ['?name=' . $input['username']]);
                $pppUser = $api->read();
                
                if (count($pppUser) > 0) {
                    // Update PPPoE user
                    $api->write('/ppp/secret/set', [
                        '.id' => $pppUser[0]['.id'],
                        'profile' => $input['profile'] ?? $pppUser[0]['profile'],
                        'limit-uptime' => $input['days'] . 'd'
                    ]);
                } else {
                    // Try Hotspot user
                    $api->write('/ip/hotspot/user/print', ['?name=' . $input['username']]);
                    $hotspotUser = $api->read();
                    
                    if (count($hotspotUser) > 0) {
                        // Update Hotspot user
                        $api->write('/ip/hotspot/user/set', [
                            '.id' => $hotspotUser[0]['.id'],
                            'profile' => $input['profile'] ?? $hotspotUser[0]['profile'],
                            'limit-uptime' => $input['days'] . 'd'
                        ]);
                    } else {
                        $api->disconnect();
                        echo json_encode(['success' => false, 'error' => 'User not found']);
                        exit;
                    }
                }
                
                $api->disconnect();
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to connect to RouterOS']);
            }
            break;
            
        case 'resetPassword':
            $api = new RouterosAPI();
            if ($api->connect($input['config']['ip'], $input['config']['username'], $input['config']['password'], ($input['config']['port'] ?? 8728))) {
                // Try PPPoE user first
                $api->write('/ppp/secret/print', ['?name=' . $input['username']]);
                $pppUser = $api->read();
                
                if (count($pppUser) > 0) {
                    // Update PPPoE password
                    $api->write('/ppp/secret/set', [
                        '.id' => $pppUser[0]['.id'],
                        'password' => $input['password']
                    ]);
                } else {
                    // Try Hotspot user
                    $api->write('/ip/hotspot/user/print', ['?name=' . $input['username']]);
                    $hotspotUser = $api->read();
                    
                    if (count($hotspotUser) > 0) {
                        // Update Hotspot password
                        $api->write('/ip/hotspot/user/set', [
                            '.id' => $hotspotUser[0]['.id'],
                            'password' => $input['password']
                        ]);
                    } else {
                        $api->disconnect();
                        echo json_encode(['success' => false, 'error' => 'User not found']);
                        exit;
                    }
                }
                
                $api->disconnect();
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to connect to RouterOS']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
