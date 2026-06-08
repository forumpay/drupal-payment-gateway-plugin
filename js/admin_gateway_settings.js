jQuery(document).ready(function($) {
  let baseUrl = '';
  if (!baseUrl) {
    const protocol = window.location.protocol;
    const host = window.location.host;
    baseUrl = protocol + "//" + host;
  }

  $('.padding-left-1-6-em').css('padding-left', '1.6em');
  $('#drupal_forumpay_api_test').css('margin', 0);

  const fields = [
    'accept-underpayment',
    'accept-overpayment',
  ];

  function initializeFieldHandlers(field) {
    $(`[id^="edit-configuration-forumpay-${field}-main"]`).on( 'change', () => {
      togglePaymentOptions(field);
    });

    $(`[id^="edit-configuration-forumpay-${field}-modify-order-total"]`).on('change', () => {
      toggleModifyOrderDescription(field);
    });
  }

  function togglePaymentOptions(field) {
    const isChecked = $(`[id^="edit-configuration-forumpay-${field}-main"]`).is(':checked');

    $(`[id^="edit-configuration-forumpay-${field}-threshold"]`).closest('div').toggle(isChecked);

    $(`[id^="edit-configuration-forumpay-${field}-modify-order-total"]`).closest('div').toggle(isChecked);

    validateThreshold(field, isChecked);
    toggleModifyOrderDescription(field);
  }

  function validateThreshold(field, isChecked) {
    const thresholdElement = $(`[id^="edit-configuration-forumpay-${field}-threshold"]`);

    if(thresholdElement.length === 0) {
      return;
    }

    if (field === 'accept-underpayment' && isChecked) {
      thresholdElement.attr('pattern', '^(?!0+(\\.0{1,2})?$)(\\d{1,2})(\\.\\d{1,2})?$');
      thresholdElement.attr('title', 'Please enter a valid percentage between 0 and 100 or leave blank to accept any underpayment amount.');
    }

    if (field === 'accept-overpayment' && isChecked) {
      thresholdElement.attr('pattern', '^(?!0+(\\.0{1,2})?$)(\\d{1,2})(\\.\\d{1,2})?$');
      thresholdElement.attr('title', 'Please enter a valid percentage between 0 and 100 or leave blank to accept any overpayment amount.');
    }

    if (!isChecked) {
      thresholdElement.removeAttr('pattern');
      thresholdElement.removeAttr('title');
    }

    thresholdElement.on('blur', function () {
      const value = this.value.trim();

      if (!isNaN(value) && value !== '') {
        const floatValue = Number(value);
        const decimals = (value.split('.')[1] || '').length;

        if (decimals > 2) {
          this.value = floatValue.toFixed(2);
        }
      }

      if (/^0\d+/.test(value) || parseFloat(value) === 0) {
        this.value = 0;
      }
    });
  }

  function toggleModifyOrderDescription(field) {
    const isModifyOrderChecked = $(`[id^="edit-configuration-forumpay-${field}-modify-order-total"]`).is(':checked');
    const isModifyOrderVisible = $(`[id^="edit-configuration-forumpay-${field}-modify-order-total"]`).is(':visible');

    $(`[id^="edit-configuration-forumpay-${field}-modify-order-total-description"]`).closest('div').toggle(isModifyOrderVisible && isModifyOrderChecked);
    $(`[id^="edit-configuration-forumpay-${field}-modify-order-total-description"]`).prop('required', isModifyOrderVisible && isModifyOrderChecked);
  }

  fields.forEach(field => {
    togglePaymentOptions(field);
    initializeFieldHandlers(field);
  });

  function toggleMerchantNotice() {
    const value = $('[id^="edit-configuration-forumpay-network-processing-fee-paid-by"]').not('[id*="merchant-notice"]').val();
    $('#edit-configuration-forumpay-network-processing-fee-paid-by-merchant-notice').toggle(value === 'merchant');
  }

  toggleMerchantNotice();

  $('[id^="edit-configuration-forumpay-network-processing-fee-paid-by"]').not('[id*="merchant-notice"]').change(toggleMerchantNotice);

  $('#drupal_forumpay_api_test').on('click', function(e) {
    e.preventDefault();

    var $button = $(this);
    var originalText = $button.text();
    $button.prop('disabled', true);
    $button.text('Testing ...');

    // You can perform AJAX calls or other logic here
    $.ajax({
      url: baseUrl + "/forumpay-api?act=ping",
      type: 'POST',
      contentType: 'application/json',
      dataType: 'json',
      data: JSON.stringify({
        apiEnv: $('#edit-configuration-forumpay-api-url').val(),
        apiKey: $('#edit-configuration-forumpay-api-user').val(),
        apiSecret: $('#edit-configuration-forumpay-api-key').val(),
        apiUrlOverride: $('#edit-configuration-forumpay-api-url-override').val(),
        webhookUrl: $('#edit-configuration-forumpay-webhook-url').val(),
      }),
      success: function(response) {
        $button.prop('disabled', false);
        $button.text(originalText);

        const {webhookSuccess, webhookPingResponse, message} = response || {};
        const {status, duration, webhookUrl, responseCode, responseBody} = webhookPingResponse || {};

        if (!webhookSuccess || !webhookPingResponse) {
          alert(`Server responded: ${message}`);
          return;
        }

        if (webhookSuccess === 'OK') {
          alert(`Server responded: ${message}\n\nWebhook responded: ${webhookSuccess}`);
          return;
        }

        alert(`Server responded: ${response?.message}\n\nWebhook responded: ${webhookSuccess}
          Status: ${status}
          Duration: ${duration} seconds
          Webhook URL: ${webhookUrl}
          ${responseCode ? `Response Code: ${responseCode}` : ''}
          ${responseBody ? `Response Body: ${responseBody}` : ''}
        `);
        },
        error: function(error) {
          $button.prop('disabled', false);
          $button.text(originalText);
          const now = new Date();

          // Extract the UTC components
          const year = now.getUTCFullYear();
          const month = String(now.getUTCMonth() + 1).padStart(2, '0'); // Months are zero-indexed, so add 1
          const day = String(now.getUTCDate()).padStart(2, '0');
          const hours = String(now.getUTCHours()).padStart(2, '0');
          const minutes = String(now.getUTCMinutes()).padStart(2, '0');
          const seconds = String(now.getUTCSeconds()).padStart(2, '0');

          // Format the date and time in UTC
          const currentDateTimeUTC = `${year}-${month}-${day} ${hours}:${minutes}:${seconds} UTC`;

          var message = '';

          if (error?.responseJSON?.code > 0) {
            message += error.responseJSON.code + ' - ';
          }

          message += error?.responseJSON?.message ?? "Unknown error occurred. Please contact support."

          message += "\n\n" + "Date: " + currentDateTimeUTC;
          if (error?.responseJSON?.cfray_id) {
            message += "\n" + "Ray Id: " + error?.responseJSON?.cfray_id;
          }

          alert(message);
        }
      });
    });
});
