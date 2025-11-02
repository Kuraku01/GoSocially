<?php
// Follow API endpoints
// Handles follow/unfollow actions and follow requests

require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/follow_functions.php';

// Require login for all follow actions
requireLogin();

// Get current user info
$currentUser = getCurrentUser();
if (!$currentUser) {
    logout();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

// Handle POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

header('Content-Type: application/json');

try {
    verifyCSRFToken($_POST['csrf_token'] ?? '');

    $action = $_POST['action'] ?? '';
    $targetUserId = (int)($_POST['user_id'] ?? 0);

    // Validate target user
    if ($targetUserId <= 0 || $targetUserId == $currentUser['id']) {
        throw new Exception("Invalid user ID");
    }

    // Verify target user exists and is active
    $targetUser = getUserById($targetUserId);
    if (!$targetUser) {
        throw new Exception("User not found");
    }

    $response = ['success' => false];

    switch ($action) {
        case 'follow':
            try {
                if (followUser($currentUser['id'], $targetUserId)) {
                    $response['success'] = true;
                    $response['message'] = 'You are now following ' . htmlspecialchars($targetUser['username']);
                    $response['action'] = 'followed';
                }
            } catch (Exception $e) {
                $response['error'] = $e->getMessage();
            }
            break;

        case 'unfollow':
            if (unfollowUser($currentUser['id'], $targetUserId)) {
                $response['success'] = true;
                $response['message'] = 'You are no longer following ' . htmlspecialchars($targetUser['username']);
                $response['action'] = 'unfollowed';
            } else {
                $response['error'] = 'Failed to unfollow user';
            }
            break;

        case 'accept_follow':
            try {
                if (acceptFollowRequest($currentUser['id'], $targetUserId)) {
                    $response['success'] = true;
                    $response['message'] = 'Follow request accepted';
                    $response['action'] = 'accepted';
                    $response['mutual'] = true;
                }
            } catch (Exception $e) {
                $response['error'] = $e->getMessage();
            }
            break;

        case 'decline_follow':
            if (declineFollowRequest($currentUser['id'], $targetUserId)) {
                $response['success'] = true;
                $response['message'] = 'Follow request declined';
                $response['action'] = 'declined';
            } else {
                $response['error'] = 'Failed to decline follow request';
            }
            break;

        case 'check_follow_status':
            $isFollowing = isFollowing($currentUser['id'], $targetUserId);
            $isFollowedByThem = isFollowing($targetUserId, $currentUser['id']);
            $areMutuals = areMutuals($currentUser['id'], $targetUserId);

            $response['success'] = true;
            $response['is_following'] = $isFollowing;
            $response['is_followed_by_them'] = $isFollowedByThem;
            $response['are_mutuals'] = $areMutuals;
            break;

        case 'get_followers':
            $limit = min((int)($_POST['limit'] ?? 50), 100);
            $offset = max((int)($_POST['offset'] ?? 0), 0);

            $followers = getFollowers($targetUserId, $limit, $offset);
            $followersList = [];

            foreach ($followers as $follower) {
                $followersList[] = [
                    'id' => $follower['id'],
                    'username' => $follower['username'],
                    'full_name' => $follower['full_name'],
                    'followed_at' => $follower['followed_at'],
                    'is_following_back' => isFollowing($targetUserId, $follower['id']),
                    'are_mutuals' => areMutuals($targetUserId, $follower['id'])
                ];
            }

            $response['success'] = true;
            $response['followers'] = $followersList;
            break;

        case 'get_following':
            $limit = min((int)($_POST['limit'] ?? 50), 100);
            $offset = max((int)($_POST['offset'] ?? 0), 0);

            $following = getFollowing($targetUserId, $limit, $offset);
            $followingList = [];

            foreach ($following as $followed) {
                $followingList[] = [
                    'id' => $followed['id'],
                    'username' => $followed['username'],
                    'full_name' => $followed['full_name'],
                    'followed_at' => $followed['followed_at'],
                    'is_followed_back' => isFollowing($followed['id'], $targetUserId),
                    'are_mutuals' => areMutuals($targetUserId, $followed['id'])
                ];
            }

            $response['success'] = true;
            $response['following'] = $followingList;
            break;

        case 'get_follow_stats':
            $stats = getFollowStats($targetUserId);
            $response['success'] = true;
            $response['stats'] = $stats;
            break;

        case 'get_follow_requests':
            if ($targetUserId != $currentUser['id']) {
                throw new Exception("You can only view your own follow requests");
            }

            $limit = min((int)($_POST['limit'] ?? 50), 100);
            $offset = max((int)($_POST['offset'] ?? 0), 0);

            $requests = getFollowRequests($targetUserId, $limit, $offset);
            $requestsList = [];

            foreach ($requests as $request) {
                $requestsList[] = [
                    'id' => $request['id'],
                    'username' => $request['username'],
                    'full_name' => $request['full_name'],
                    'request_date' => $request['request_date']
                ];
            }

            $response['success'] = true;
            $response['requests'] = $requestsList;
            break;

        default:
            throw new Exception("Invalid action");
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>