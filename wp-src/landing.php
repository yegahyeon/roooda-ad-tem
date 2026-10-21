<?php
/**
 * 루다 검색광고 랜딩 — 전체 화면 페이지 템플릿
 * 테마의 헤더·푸터 없이 랜딩만 출력합니다. (build-wp.js 가 index.html 에서 자동 생성)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ( ! current_theme_supports( 'title-tag' ) ) : ?>
<title><?php echo esc_html( '{{TITLE}}' ); ?></title>
<?php endif; ?>
<?php wp_head(); ?>
</head>
<body <?php body_class( 'ruda-landing' ); ?>>
<?php wp_body_open(); ?>
{{MARKUP}}
<?php wp_footer(); ?>
</body>
</html>
