/**
 * Prairie Landing — Menu Admin Quick Edit
 *
 * Populates the Quick Edit price field from the admin column value
 * so editors can change prices without opening the full edit screen.
 *
 * @package PLGC
 * @since   1.7.26
 */
(function ($) {
    'use strict';

    var origInlineEdit = inlineEditPost.edit;

    inlineEditPost.edit = function (id) {
        // Call the original handler
        origInlineEdit.apply(this, arguments);

        // Get the post ID
        if (typeof id === 'object') {
            id = this.getId(id);
        }

        var $row      = $('#post-' + id);
        var $editRow  = $('#edit-' + id);

        // Read price from the admin column
        var priceText = $row.find('.column-menu_price').text().trim();
        // Extract the first dollar amount (base price)
        var match = priceText.match(/^\$?([\d.]+)/);
        var price = match ? match[1] : '';

        $editRow.find('input[name="menu_item_price"]').val(price);
    };
})(jQuery);
