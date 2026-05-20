$templates = @{
    "manual_order_received_customer" = @{
        "subject" = "Your Order #{{order_number}} is Received! - Cakeouflage"
        "body" = "<html><body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'><div style='max-width: 600px; margin: auto; border: 1px solid #ddd; padding: 20px;'><h2 style='color: #e91e63;'>Order Received</h2><p>Hi {{customer_name}},</p><p>We have received your order <strong>#{{order_number}}</strong>. Our team is reviewing it and will get back to you soon.</p><table style='width: 100%; border-collapse: collapse;'><tr><td style='padding: 8px; border-bottom: 1px solid #eee;'><strong>Items:</strong></td><td style='padding: 8px; border-bottom: 1px solid #eee;'>{{item_names}}</td></tr><tr><td style='padding: 8px; border-bottom: 1px solid #eee;'><strong>Total:</strong></td><td style='padding: 8px; border-bottom: 1px solid #eee;'>{{grand_total}}</td></tr></table><p>We will contact you at {{customer_phone}} or {{customer_email}} if needed.</p><p>Thanks for choosing Cakeouflage!</p></div></body></html>"
    }
    "payment_confirmed_customer" = @{
        "subject" = "Payment Confirmed for Order #{{order_number}} - Cakeouflage"
        "body" = "<html><body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'><div style='max-width: 600px; margin: auto; border: 1px solid #ddd; padding: 20px;'><h2 style='color: #4caf50;'>Payment Confirmed</h2><p>Hi {{customer_name}},</p><p>Great news! Your payment for order <strong>#{{order_number}}</strong> has been confirmed.</p><p>We are now preparing your delicious treats: {{item_names}}.</p><p><strong>Total Paid:</strong> {{grand_total}}</p><p>Stay tuned for further updates!</p></div></body></html>"
    }
    "reject_order_customer" = @{
        "subject" = "Update regarding your Order #{{order_number}} - Cakeouflage"
        "body" = "<html><body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'><div style='max-width: 600px; margin: auto; border: 1px solid #ddd; padding: 20px;'><h2 style='color: #f44336;'>Order Update</h2><p>Hi {{customer_name}},</p><p>We regret to inform you that we are unable to process your order <strong>#{{order_number}}</strong> at this time.</p><p>Items involved: {{item_names}}.</p><p>If payment was made, a refund will be processed shortly. For questions, contact us at {{customer_email}}.</p></div></body></html>"
    }
    "ready_order_customer" = @{
        "subject" = "Your Order #{{order_number}} is Ready! - Cakeouflage"
        "body" = "<html><body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'><div style='max-width: 600px; margin: auto; border: 1px solid #ddd; padding: 20px;'><h2 style='color: #2196f3;'>Order Ready</h2><p>Hi {{customer_name}},</p><p>Exciting news! Your order <strong>#{{order_number}}</strong> ({{item_names}}) is now ready for pickup/delivery.</p><p>Please have your order number ready. Thank you for choosing Cakeouflage!</p></div></body></html>"
    }
}

foreach ($key in $templates.Keys) {
    $subject = $templates[$key]["subject"].Replace("'", "''")
    $body = $templates[$key]["body"].Replace("'", "''")
    & psql -d cakeouflage -c "UPDATE communication_templates SET subject = '$subject', body_template = '$body' WHERE event_key = '$key' AND channel = 'email';"
}

& psql -d cakeouflage -c "SELECT id, event_key, subject, LEFT(body_template, 120) FROM communication_templates WHERE event_key IN ('manual_order_received_customer', 'payment_confirmed_customer', 'reject_order_customer', 'ready_order_customer') AND channel = 'email';"
