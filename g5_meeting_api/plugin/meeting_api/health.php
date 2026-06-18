<?php
/**
 * GET /plugin/meeting_api/health.php
 *
 * 의존성 점검: PHP, gnuboard5 설치 여부, DB 연결, 대상 게시판 존재 여부.
 */
require_once __DIR__ . '/_bootstrap.php';
require_method('GET');
require_auth();

$report = [
    'php_version' => PHP_VERSION,
    'g5_path' => G5_PATH_OVERRIDE,
    'g5_path_exists' => is_dir(G5_PATH_OVERRIDE),
    'g5_installed' => is_file(G5_PATH_OVERRIDE . '/data/dbconfig.php'),
    'bo_table' => meeting_BO_TABLE,
];

if (!$report['g5_installed']) {
    api_respond(503, array_merge(['ok' => false, 'error' => 'gnuboard5 not installed'], $report));
}

require_once __DIR__ . '/_load_gnuboard5.php';

global $g5;
$db_ok = sql_fetch("SELECT 1 AS v");
$report['db_connected'] = ($db_ok && $db_ok['v'] == 1);

$bo_table = meeting_normalize_bo_table(meeting_BO_TABLE);
$bo_table_esc = meeting_sql_escape($bo_table);
$board_table_sql = meeting_sql_identifier($g5['board_table']);
$board = sql_fetch("SELECT bo_table, bo_subject FROM $board_table_sql WHERE bo_table = '$bo_table_esc'");
$report['board_exists'] = (bool)$board;
$report['board_subject'] = $board['bo_subject'] ?? null;

// 작성자 모드: meeting_MB_ID 설정 시 회원 글, 아니면 비회원(게스트) 글.
$mb_id = trim((string)meeting_MB_ID);
if ($mb_id === '') {
    $report['author_mode'] = 'guest';
    $report['mb_id'] = null;
    $report['member_account_exists'] = null;
} else {
    $report['author_mode'] = 'member';
    $report['mb_id'] = $mb_id;
    $member_table_sql = meeting_sql_identifier($g5['member_table']);
    $mb_id_esc = meeting_sql_escape($mb_id);
    $member = sql_fetch("SELECT mb_id, mb_leave_date, mb_intercept_date
        FROM $member_table_sql WHERE mb_id = '$mb_id_esc'");
    $report['member_account_exists'] = (bool)$member;
    // 회원 모드인데 계정이 없거나 차단 상태면 글 작성이 실패하므로 경고를 띄운다.
    if (!$member) {
        $report['member_warning'] = "meeting_MB_ID '$mb_id' 회원이 없습니다. setup_member.php 로 봇 계정을 먼저 생성하세요.";
    } elseif (!empty($member['mb_leave_date']) || !empty($member['mb_intercept_date'])) {
        $report['member_warning'] = "meeting_MB_ID '$mb_id' 계정이 탈퇴/차단 상태입니다.";
    }
}

api_ok($report);
