<?php
/**
 * Plugin Name:       루다애드 검색광고 랜딩
 * Description:       검색광고 무료 진단 랜딩 페이지. 페이지 편집 화면 오른쪽 '템플릿'에서 "루다 검색광고 랜딩 (전체 화면)"을 고르면 적용됩니다. 신청 내역은 관리자 메일로 발송되고 '무료 진단 신청' 메뉴에도 저장됩니다.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            루다애드
 * Text Domain:       ruda-landing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RUDA_LANDING_VER', '1.0.0' );
define( 'RUDA_LANDING_TPL', 'ruda-landing.php' );
define( 'RUDA_LANDING_URL', plugin_dir_url( __FILE__ ) );
define( 'RUDA_LANDING_DIR', plugin_dir_path( __FILE__ ) );

/* ------------------------------------------------------------------
 * 1. 페이지 템플릿 등록 → 선택된 페이지만 랜딩으로 출력
 * ------------------------------------------------------------------ */
add_filter(
	'theme_page_templates',
	function ( $templates ) {
		$templates[ RUDA_LANDING_TPL ] = '루다 검색광고 랜딩 (전체 화면)';
		return $templates;
	}
);

function ruda_landing_is_active() {
	return is_page() && get_page_template_slug( get_queried_object_id() ) === RUDA_LANDING_TPL;
}

add_filter(
	'template_include',
	function ( $template ) {
		return ruda_landing_is_active() ? RUDA_LANDING_DIR . 'templates/landing.php' : $template;
	},
	99
);

// 랜딩에서는 관리자 바 숨김 (상단 고정 요소와 겹치지 않게)
add_action(
	'template_redirect',
	function () {
		if ( ruda_landing_is_active() ) {
			show_admin_bar( false );
		}
	}
);

/* ------------------------------------------------------------------
 * 2. CSS / JS 로드 + 테마 CSS 제거 (랜딩 페이지에서만)
 * ------------------------------------------------------------------ */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! ruda_landing_is_active() ) {
			return;
		}

		$fonts = array(
		{{FONTS}}
		);
		foreach ( $fonts as $i => $href ) {
			wp_enqueue_style( 'ruda-landing-font-' . $i, $href, array(), null );
		}
		wp_enqueue_style( 'ruda-landing', RUDA_LANDING_URL . 'assets/css/landing.css', array(), RUDA_LANDING_VER );

		$libs = array(
		{{LIBS}}
		);
		$deps = array();
		foreach ( $libs as $i => $src ) {
			$handle = 'ruda-landing-lib-' . $i;
			wp_enqueue_script( $handle, $src, $deps, null, true );
			$deps[] = $handle;
		}
		wp_enqueue_script( 'ruda-landing', RUDA_LANDING_URL . 'assets/js/landing.js', $deps, RUDA_LANDING_VER, true );
		wp_localize_script(
			'ruda-landing',
			'rudaLanding',
			array(
				'base'    => RUDA_LANDING_URL . 'assets/',
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	},
	20
);

// 테마·블록 기본 CSS 가 랜딩 레이아웃을 깨지 않도록 제거
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! ruda_landing_is_active() ) {
			return;
		}
		global $wp_styles;
		$theme_urls = array_unique( array( get_template_directory_uri(), get_stylesheet_directory_uri() ) );
		foreach ( (array) $wp_styles->queue as $handle ) {
			$src = isset( $wp_styles->registered[ $handle ] ) ? (string) $wp_styles->registered[ $handle ]->src : '';
			foreach ( $theme_urls as $url ) {
				if ( $src && 0 === strpos( $src, $url ) ) {
					wp_dequeue_style( $handle );
				}
			}
		}
		foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'global-styles', 'classic-theme-styles' ) as $handle ) {
			wp_dequeue_style( $handle );
		}
	},
	9999
);

/* ------------------------------------------------------------------
 * 3. 신청 내역 저장용 관리자 메뉴 ('무료 진단 신청')
 * ------------------------------------------------------------------ */
add_action(
	'init',
	function () {
		register_post_type(
			'ruda_apply',
			array(
				'labels'       => array(
					'name'          => '무료 진단 신청',
					'singular_name' => '무료 진단 신청',
					'all_items'     => '신청 목록',
					'edit_item'     => '신청 내용',
					'search_items'  => '신청 검색',
					'not_found'     => '아직 신청이 없습니다.',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'menu_icon'    => 'dashicons-email-alt',
				'supports'     => array( 'title', 'editor' ),
				'capabilities' => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap' => true,
			)
		);
	}
);

/* ------------------------------------------------------------------
 * 4. 신청 폼 처리 (admin-ajax)
 *    받는 메일 주소 변경:  add_filter( 'ruda_landing_mail_to', fn() => 'you@example.com' );
 * ------------------------------------------------------------------ */
add_action( 'wp_ajax_ruda_landing_apply', 'ruda_landing_apply' );
add_action( 'wp_ajax_nopriv_ruda_landing_apply', 'ruda_landing_apply' );

function ruda_landing_apply() {
	// 스팸 방지: 사람에게 안 보이는 칸이 채워져 있으면 봇
	if ( ! empty( $_POST['website'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		wp_send_json_success();
	}

	// 같은 IP 에서 30초 안 재전송 차단
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'ruda_apply_' . md5( $ip );
	if ( get_transient( $key ) ) {
		wp_send_json_error( '잠시 후 다시 시도해주세요.', 429 );
	}

	// phpcs:disable WordPress.Security.NonceVerification
	$company = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$budget  = isset( $_POST['budget'] ) ? sanitize_text_field( wp_unslash( $_POST['budget'] ) ) : '';
	$agree   = ! empty( $_POST['agree'] );
	// phpcs:enable

	if ( '' === $company || '' === $budget || ! $agree || ! preg_match( '/^0\d{1,2}-?\d{3,4}-?\d{4}$/', $phone ) ) {
		wp_send_json_error( '입력값을 확인해주세요.', 400 );
	}

	set_transient( $key, 1, 30 );

	$lines = array(
		'회사명 / 브랜드명 : ' . $company,
		'연락처 : ' . $phone,
		'월 평균 검색광고 예산 : ' . $budget,
		'개인정보 수집 동의 : 예',
		'신청 시각 : ' . wp_date( 'Y-m-d H:i' ),
		'신청 페이지 : ' . esc_url_raw( (string) wp_get_referer() ),
	);

	wp_insert_post(
		array(
			'post_type'    => 'ruda_apply',
			'post_status'  => 'private',
			'post_title'   => $company . ' (' . $phone . ')',
			'post_content' => implode( "\n", $lines ),
		)
	);

	$to = apply_filters( 'ruda_landing_mail_to', get_option( 'admin_email' ) );
	wp_mail( $to, '[루다애드] 무료 진단 신청 - ' . $company, implode( "\n", $lines ) );

	wp_send_json_success();
}
