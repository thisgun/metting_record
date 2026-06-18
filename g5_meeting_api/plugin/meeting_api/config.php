<?php
/**
 * g5_meeting_api 설정 (그누보드5 plugin/meeting_api 표준 배치)
 *
 * 위치: <그누보드5루트>/plugin/meeting_api/
 *
 * 그누보드5 경로는 plugin 폴더의 2단계 상위로 자동 결정됨.
 * 별도 설정 없이 어디에 설치되어 있든 동작.
 *
 * 운영 토큰 분리:
 * - 같은 폴더에 config.local.php 를 만들어 define() 으로 토큰을 정의하면
 *   이 파일의 기본값을 덮어쓴다. config.local.php 는 git에 올리지 않는다.
 */

// 운영 환경 오버라이드 먼저 로드 (정의된 값은 아래 if (!defined())에서 보존)
if (is_file(__DIR__ . '/config.local.php')) {
    @include_once(__DIR__ . '/config.local.php');
}

// 그누보드5 경로 — plugin/meeting_api/ 의 2단계 상위가 그누보드5 루트
if (!defined('G5_PATH_OVERRIDE')) {
    $__root = realpath(__DIR__ . '/../..');
    if (!$__root) $__root = dirname(__DIR__, 2);
    define('G5_PATH_OVERRIDE', $__root);
}

// 기본 설정 (config.local.php에서 덮어쓸 수 있음)
if (!defined('meeting_BO_TABLE')) define('meeting_BO_TABLE', 'meeting');
if (!defined('meeting_API_TOKEN')) define('meeting_API_TOKEN', 'change-me-please-use-strong-random-token');
// 회의록봇 회원 계정. 값을 채우면 글/댓글이 해당 회원 글로 등록된다(권장).
// 비워두면 비회원(게스트) 글로 등록된다(하위 호환).
// 계정은 setup_member.php 로 자동 생성하거나 그누보드 관리자에서 만들 수 있다.
if (!defined('meeting_MB_ID')) define('meeting_MB_ID', '');
// setup_member.php 가 봇 계정을 만들 때 쓰는 비밀번호. 비우면 임의 생성 후 1회 응답에 표시.
if (!defined('meeting_MB_PASSWORD')) define('meeting_MB_PASSWORD', '');
// setup_member.php 가 봇 계정에 부여할 회원 레벨(대상 게시판 글쓰기 레벨 이상이어야 함).
// 기본 2(일반 회원). 게시판 글쓰기 레벨이 더 높으면 그 값 이상으로 올리세요.
if (!defined('meeting_MB_LEVEL')) define('meeting_MB_LEVEL', 2);
if (!defined('meeting_WR_NAME')) define('meeting_WR_NAME', '회의록봇');
// 비회원(게스트) 글의 수정/삭제 비밀번호. 저장소에 공개된 고정 비밀번호는 위험하므로
// 기본값은 빈 문자열(게스트 폼에서 수정/삭제 불가). 회원 글(meeting_MB_ID 설정 시)에는 사용하지 않는다.
if (!defined('meeting_WR_PASSWORD')) define('meeting_WR_PASSWORD', '');
if (!defined('meeting_WR_EMAIL')) define('meeting_WR_EMAIL', '');
if (!defined('meeting_WR_HOMEPAGE')) define('meeting_WR_HOMEPAGE', '');
if (!defined('meeting_API_MARKER')) define('meeting_API_MARKER', 'meeting_api');
if (!defined('meeting_API_ALLOW_UNMARKED_WRITES')) define('meeting_API_ALLOW_UNMARKED_WRITES', false);
if (!defined('meeting_API_ALLOW_SETUP')) define('meeting_API_ALLOW_SETUP', false);
if (!defined('meeting_API_ALLOWED_IPS')) define('meeting_API_ALLOWED_IPS', '');
if (!defined('meeting_API_MAX_BODY_BYTES')) define('meeting_API_MAX_BODY_BYTES', 3145728); // 3 MiB
if (!defined('meeting_API_MAX_POST_CONTENT_BYTES')) define('meeting_API_MAX_POST_CONTENT_BYTES', 2097152); // 2 MiB
if (!defined('meeting_API_MAX_COMMENT_CONTENT_BYTES')) define('meeting_API_MAX_COMMENT_CONTENT_BYTES', 262144); // 256 KiB
// 공개 게시판 URL 자동 계산이 호스팅 환경에서 어긋나면 config.local.php에서
// 예: define('meeting_PUBLIC_BASE_URL', 'https://example.com/gnu5624');
if (!defined('meeting_PUBLIC_BASE_URL')) define('meeting_PUBLIC_BASE_URL', '');

// 운영 안전을 위해 디버그는 기본 false. 필요할 때 config.local.php에서 명시적으로 true 지정.
if (!defined('meeting_API_DEBUG')) define('meeting_API_DEBUG', false);
