/**
 * Custom modal scripting
 * @returns
 */
jQuery( function() {

	jQuery('.um-preview-registration').umModal({
		header: wp.i18n.__( 'Review Registration Details', 'ultimate-member' ),
		content: function( event, options ) {
			let $modal = this;
			let $btn = jQuery( event.currentTarget );

			return wp.ajax.send( 'um_admin_review_registration', {
				data: {
					user_id: $btn.data( 'user_id' ),
					nonce: um_admin_scripts.nonce
				}
			});
		}
	});

});
