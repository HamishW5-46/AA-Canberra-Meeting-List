<?php

function aac_feedback_render_gso_status($result)
{
    $success = !empty($result->success);
    $heading = __('GSO submission status', 'aa-canberra-meeting-list');
    $label = $success ? __('Submitted', 'aa-canberra-meeting-list') : __('Manual intervention required', 'aa-canberra-meeting-list');
    $border = $success ? '#16a34a' : '#dc2626';
    $background = $success ? '#f0fdf4' : '#fef2f2';
    $text = $success ? '#14532d' : '#7f1d1d';

    $html = aac_feedback_render_heading($heading);
    $html .= '<div style="background:' . esc_attr($background) . ';border:1px solid ' . esc_attr($border) . ';margin:0 0 8px;padding:12px;">';
    $html .= '<p style="color:' . esc_attr($text) . ';font-size:14px;font-weight:700;line-height:1.5;margin:0 0 4px;">' . esc_html($label) . '</p>';
    $html .= '<p style="color:' . esc_attr($text) . ';font-size:14px;line-height:1.5;margin:0;">' . esc_html($result->message) . '</p>';

    if (!empty($result->http_status) || !empty($result->error)) {
        $details = [];
        if (!empty($result->http_status)) {
            $details[] = sprintf(__('HTTP %s', 'aa-canberra-meeting-list'), intval($result->http_status));
        }
        if (!empty($result->error)) {
            $details[] = aac_feedback_plain_text($result->error);
        }
        $html .= '<p style="color:#374151;font-size:12px;line-height:1.4;margin:8px 0 0;">' . esc_html(implode(' | ', $details)) . '</p>';
    }

    if (!$success && !empty($result->body)) {
        $body = substr(aac_feedback_plain_text($result->body), 0, 500);
        if ($body) {
            $html .= '<p style="color:#374151;font-size:12px;line-height:1.4;margin:8px 0 0;">' . esc_html($body) . '</p>';
        }
    }

    $html .= '</div>';

    return $html;
}
