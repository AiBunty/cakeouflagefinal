<?php
declare(strict_types=1);

if (!function_exists('render_master_email_layout')) {
    /**
     * Render the shared branded HTML shell used by communication email templates.
     *
     * @param array<string,mixed> $cfg
     */
    function render_master_email_layout(array $cfg): string
    {
        $accent = (string)($cfg['accent'] ?? '#80001F');
        $bg = (string)($cfg['bg'] ?? '#f5eef2');
        $panelBg = (string)($cfg['panel_bg'] ?? '#fff7f9');
        $panelBorder = (string)($cfg['panel_border'] ?? '#f0d7df');
        $footer = (string)($cfg['footer'] ?? '#140b0f');
        $tagline = (string)($cfg['tagline'] ?? '{{business_name}} Notifications');
        $heading = (string)($cfg['heading'] ?? 'Hi {{customer_name}}');
        $lead = (string)($cfg['lead'] ?? 'Cakeouflage has an update for you.');
        $notice = (string)($cfg['notice'] ?? '');
        $detailsHtml = trim((string)($cfg['details_html'] ?? ''));
        $ctaHtml = '';
        if (!empty($cfg['cta_text'])) {
            $ctaBg = !empty($cfg['cta_bg']) ? (string)$cfg['cta_bg'] : $accent;
            $ctaHtml = '<div style="margin-top:30px;"><a href="' . htmlspecialchars((string)($cfg['cta_link'] ?? '#'), ENT_QUOTES, 'UTF-8') . '" style="background:' . $ctaBg . ';color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;">' . htmlspecialchars((string)$cfg['cta_text'], ENT_QUOTES, 'UTF-8') . '</a></div>';
        }

        $detailsSection = $detailsHtml !== ''
            ? '<div style="margin-top:28px;background:' . $panelBg . ';border:1px solid ' . $panelBorder . ';border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;">' . $detailsHtml . '</div>'
            : '';
        $noticeSection = $notice !== ''
            ? '<div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">' . $notice . '</div>'
            : '';

        return '<div style="background:' . $bg . ';padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:' . $accent . ';padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="{{business_logo}}" alt="{{business_name}} Logo" style="height:72px;display:block;width:auto;max-width:260px;background:rgba(255,255,255,0.96);padding:10px 16px;border-radius:16px;box-sizing:border-box;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">' . $tagline . '</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">' . $heading . '</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">' . $lead . '</p>' . $detailsSection . $noticeSection . $ctaHtml . '</div><div style="background:' . $footer . ';padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team {{business_name}}</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; <a href="{{business_website}}" style="color:#f5d8e2;text-decoration:underline;">{{business_website}}</a></p><p style="color:#d7c6cc;font-size:14px;">&#9993; {{support_email}} &nbsp;|&nbsp; &#128222; {{support_phone}}</p><p style="color:#d7c6cc;font-size:14px;">&#128222; WhatsApp: <a href="{{support_whatsapp_url}}" style="color:#f5d8e2;text-decoration:underline;">{{support_whatsapp}}</a></p></div></div></div>';
    }
}
