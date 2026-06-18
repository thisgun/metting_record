<?php
/**
 * 회의록봇 회원 계정 자동 생성/검증 (1회 실행).
 *
 * 비공개(회원제) 게시판에서는 글이 "로그인한 회원"으로 등록돼야 자연스럽다.
 * 이 엔드포인트는 meeting_MB_ID 로 지정한 봇 계정을 g5_member 에 만들어 준다.
 * 이후 post.php/comment.php 가 그 계정의 글로 등록한다(게스트 글 아님).
 *
 * 사용:
 *   1. config.local.php 에 meeting_MB_ID(예: 'meetingbot') 와
 *      meeting_API_ALLOW_SETUP=true 지정. (선택) meeting_MB_PASSWORD 지정.
 *   2. X-API-Token 헤더를 포함한 POST 요청으로 1회 호출.
 *   3. 응답 확인 후 meeting_API_ALLOW_SETUP 를 false 로 되돌리거나 이 파일 삭제.
 *
 * 동작:
 *   - 회원이 이미 있으면 그대로 둠(idempotent).
 *   - 없으면 g5_member 에 봇 계정 1건 생성(이메일 인증 완료 상태).
 *
 * 보안: 인증 토큰 일치 + meeting_API_ALLOW_SETUP=true 일 때만 동작.
 */
require_once __DIR__ . '/_bootstrap.php';
require_method('POST');
require_auth();

if (!defined('meeting_API_ALLOW_SETUP') || !meeting_API_ALLOW_SETUP) {
    api_error(
        403,
        'setup_member.php is disabled. Set meeting_API_ALLOW_SETUP=true in config.local.php only during initial setup.'
    );
}

$m_mb_id = trim((string)meeting_MB_ID);
if ($m_mb_id === '') {
    api_error(400, 'meeting_MB_ID 가 비어 있습니다. config.local.php 에서 봇 계정 아이디를 먼저 지정하세요 (예: define(\'meeting_MB_ID\', \'meetingbot\');).');
}
if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $m_mb_id)) {
    api_error(400, 'meeting_MB_ID 는 영문/숫자/underscore 3~20자여야 합니다.');
}

require_once __DIR__ . '/_load_gnuboard5.php';
global $g5;

$member_table_sql = meeting_sql_identifier($g5['member_table']);
$mb_id_esc = meeting_sql_escape($m_mb_id);

$report = [
    'g5_path' => G5_PATH_OVERRIDE,
    'mb_id' => $m_mb_id,
];

$existing = sql_fetch("SELECT mb_id, mb_nick, mb_level, mb_leave_date, mb_intercept_date
    FROM $member_table_sql WHERE mb_id = '$mb_id_esc'");

if ($existing) {
    $report['member_existed'] = true;
    $report['mb_nick'] = $existing['mb_nick'];
    $report['mb_level'] = (int)$existing['mb_level'];
    $report['is_blocked'] = (!empty($existing['mb_leave_date']) || !empty($existing['mb_intercept_date']));
    api_ok([
        'message' => '봇 회원 계정이 이미 존재합니다. 그대로 사용합니다.',
        'report' => $report,
    ]);
}

// --- 신규 생성 ---
$nick = trim((string)meeting_WR_NAME);
if ($nick === '') $nick = $m_mb_id;
$nick = mb_substr($nick, 0, 20, 'UTF-8');

$email = trim((string)meeting_WR_EMAIL);  // 비어 있어도 됨

$level = (int)(defined('meeting_MB_LEVEL') ? meeting_MB_LEVEL : 10);
if ($level < 1) $level = 1;
if ($level > 10) $level = 10;

// 비밀번호: 지정값 우선, 없으면 임의 생성(응답에 1회 표시).
$plain = trim((string)(defined('meeting_MB_PASSWORD') ? meeting_MB_PASSWORD : ''));
$generated = false;
if ($plain === '') {
    $plain = bin2hex(random_bytes(12));  // 24 hex chars
    $generated = true;
}
if (function_exists('get_encrypt_string')) {
    $hash = get_encrypt_string($plain);   // 그누보드 회원 비밀번호 해시
} elseif (function_exists('sql_password')) {
    $hash = sql_password($plain);
} else {
    api_error(500, 'Server misconfigured: password hashing function unavailable.');
}

$now = G5_TIME_YMDHIS;
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

$nick_esc = meeting_sql_escape($nick);
$name_esc = meeting_sql_escape($nick);  // 봇은 이름=닉네임으로 둠
$email_esc = meeting_sql_escape($email);
$hash_esc = meeting_sql_escape($hash);
$now_esc = meeting_sql_escape($now);
$ip_esc = meeting_sql_escape($ip);

// 닉네임 중복 방지(그누보드는 PHP 단에서만 강제하므로 직접 확인).
$nick_dup = sql_fetch("SELECT mb_id FROM $member_table_sql WHERE mb_nick = '$nick_esc' LIMIT 1");
if ($nick_dup) {
    api_error(409, "닉네임 '$nick' 이(가) 이미 다른 회원과 중복됩니다. config.local.php 의 meeting_WR_NAME 을 변경하세요.");
}

// NOT NULL 컬럼이 많아 sql_mode 를 완화하고 핵심 컬럼만 INSERT(나머지는 기본값).
meeting_sql_query_or_error("SET SESSION sql_mode = ''", 'Failed to set SQL mode');

$sql = "INSERT INTO $member_table_sql SET
    mb_id = '$mb_id_esc',
    mb_password = '$hash_esc',
    mb_name = '$name_esc',
    mb_nick = '$nick_esc',
    mb_nick_date = '$now_esc',
    mb_email = '$email_esc',
    mb_level = '$level',
    mb_mailling = 0,
    mb_sms = 0,
    mb_open = 0,
    mb_point = 0,
    mb_email_certify = '$now_esc',
    mb_datetime = '$now_esc',
    mb_today_login = '$now_esc',
    mb_login_ip = '$ip_esc',
    mb_ip = '$ip_esc'";

meeting_sql_query_or_error($sql, 'Failed to create bot member');

$report['member_created'] = true;
$report['mb_nick'] = $nick;
$report['mb_level'] = $level;
$report['mb_email'] = $email;

$result = [
    'message' => '봇 회원 계정을 생성했습니다. 이제 글/댓글이 이 계정의 회원 글로 등록됩니다.',
    'report' => $report,
    'next_steps' => [
        '1. config.local.php 의 meeting_API_ALLOW_SETUP 을 false 로 되돌리거나 setup_member.php / setup_board.php 삭제',
        '2. health.php 응답의 author_mode 가 "member" 인지 확인',
        '3. 새 회의 요약을 등록해 글 작성자가 봇 계정으로 표시되는지 확인',
    ],
];

if ($generated) {
    $result['generated_password'] = $plain;
    $result['password_warning'] = '임의 생성된 비밀번호입니다. 이 응답에서만 표시되니 안전한 곳에 보관하세요. 봇 계정으로 웹 로그인이 필요 없으면 무시해도 됩니다.';
}

api_ok($result);
