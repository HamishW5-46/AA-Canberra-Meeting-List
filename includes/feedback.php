<?php

function aac_feedback_plain_text($value)
{
    $value = html_entity_decode((string) $value, ENT_QUOTES, get_bloginfo('charset'));
    $value = wp_strip_all_tags($value);
    $value = preg_replace('/[ \t]+/', ' ', $value);
    $value = preg_replace('/\R[ \t]+/', "\n", $value);
    return trim($value);
}

function aac_feedback_render_heading($heading)
{
    return '<p style="margin:24px 0 8px;color:#111827;font-size:13px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;">' . esc_html($heading) . '</p>';
}
