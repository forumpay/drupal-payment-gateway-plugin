jQuery(document).ready(function($) {
    let baseUrl = '';
    if (!baseUrl) {
        const protocol = window.location.protocol;
        const host = window.location.host;
        baseUrl = protocol + "//" + host;
    }

    const pathParts = window.location.pathname.split('/');
    const orderId = pathParts[pathParts.length - 1];

    const paymentIdElement = $('.order_payment_id').first();
    const paymentId = paymentIdElement.text();

    $('#forumpay-api-sync-payment').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var originalText = $button.text();
        $button.prop('disabled', true);
        $button.text('Syncing ...');

        $.ajax({
            url: baseUrl + "/forumpay-api?act=syncPayment",
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                orderId: orderId,
                payment_id: paymentId,
            }),
            success: function(response) {
                $button.prop('disabled', false);
                $button.text(originalText);
                if (response?.order_status_changed) {
                    alert('Order status updated to: ' + response?.status);
                } else {
                    alert('No updates');
                }
                window.location.reload();
            },
            error: function(error) {
                $button.prop('disabled', false);
                $button.text(originalText);

                var message = "Unknown error occurred. Please contact support.";
                alert(message);
            }
        });
    });
});
