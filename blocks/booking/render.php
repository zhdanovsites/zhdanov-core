<?php
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
if ( ! zhdanov_booking_on() ) {
	if ( wp_is_serving_rest_request() ) {
		echo '<p>Включите «Своя онлайн-запись по расписанию» в Жданов → Настройки и укажите «Мест для онлайн-записи» у занятий.</p>';
	}
	return;
}
echo zhdanov_booking_html( (int) $attributes['classId'], $attributes['title'], get_block_wrapper_attributes( array( 'class' => 'zh-booking' ) ) ); // phpcs:ignore
