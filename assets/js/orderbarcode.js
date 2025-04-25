jQuery(document).ready(function($) {
    $('#orderbarcode-search-form').on('submit', function(e) {
        e.preventDefault();
        var orderId = $('#orderbarcode-search-input').val();
        var nonce = orderbarcode_ajax.nonce;

        $.ajax({
            url: orderbarcode_ajax.ajaxurl,
            method: 'POST',
            data: {
                action: 'orderbarcode_search',
                nonce: nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    var items = response.data.items || [];
                    var $itemsList = $('#orderbarcode-items-list');
                    $itemsList.empty();

                    if (items.length === 0) {
                        $itemsList.append('<tr><td colspan="4">' + (orderbarcode_ajax.no_items_text || 'No items found') + '</td></tr>');
                    } else {
                        items.forEach(function(item) {
                            var barcodeSvg = '';
                            if (item.ean && item.ean !== 'blank') {
                                barcodeSvg = '<svg class="ean" jsbarcode-format="ean13" jsbarcode-value="' + item.ean + '" jsbarcode-textmargin="0" jsbarcode-height="50"></svg>';
                            } else {
                                barcodeSvg = '<span class="barcode-blank">No barcode</span>';
                            }
                            var row = '<tr>' +
                                '<td>' + item.sku + '</td>' +
                                '<td>' + item.name + '</td>' +
                                '<td>' + item.quantity + '</td>' +
                                '<td>' + barcodeSvg + '</td>' +
                                '</tr>';
                            $itemsList.append(row);
                        });
                        JsBarcode(".ean").init();
                    }
                    $('#orderbarcode-order-details').removeClass('hidden');
                } else {
                    var errorMsg = response.data.message || (orderbarcode_ajax.error_text || 'An error occurred');
                    console.error('AJAX error:', errorMsg);
                    alert(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = (orderbarcode_ajax.error_text || 'An error occurred') + ': ' + error;
                console.error('AJAX request failed:', xhr.responseText);
                alert(errorMsg);
            }
        });
    });

    // Add click handler for latest orders list
    $('#orderbarcode-latest-list').on('click', '.orderbarcode-latest-order', function(e) {
        e.preventDefault();
        var orderId = $(this).data('order-id');
        $('#orderbarcode-search-input').val(orderId);
        $('#orderbarcode-search-form').submit();
    });
});
