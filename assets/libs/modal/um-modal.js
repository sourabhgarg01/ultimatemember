/*!
 * UM-Modal
 *
 * @author  Ultimate Member
 * @since   UM 3.0
 * @version 1.0.1
 *
 * @link    https://docs.ultimatemember.com/
 */

(function($) {
	/**
	 * The UM-Modal library constructor
	 * @returns {ModalManagerUM}
	 */
	function ModalManagerUM() {
		// An array of modals
		this.M = [];

		// Default options
		this.defaultOptions = {
			attributes: {},
			classes: "",
			duration: 400, // ms
			footer: "",
			header: "",
			size: "normal", // small, normal, large
			template: "",
			content: ""
		};

		// Default template
		this.defaultTemplate =
			'<div class="um-modal"><span class="um-modal-close">&times;</span><div class="um-modal-header"></div><div class="um-modal-body"></div><div class="um-modal-footer"></div></div>';
	}

	ModalManagerUM.prototype = {
		constructor: ModalManagerUM,

		/**
		 * Add and display a modal
		 * @param   {object} options  Modal properties. Optional.
		 * @param   {object} event    jQuery event object. Optional.
		 * @returns {object}          A modal jQuery object.
		 */
		addModal: function(options, event) {
			options = this.filterOptions(options);

			/* Template */
			let $modal;
			if (options.template) {
				// Custom template
				let template = wp.template(options.template);
				if (template) {
					$modal = $(template(options));
				}
			}
			if (!$modal) {
				// Default template
				$modal = $(this.defaultTemplate);
			}

			/* Header and footer */
			if (options.header) {
				$modal.find(".um-modal-header").html(options.header);
			} else {
				$modal.find(".um-modal-header:empty").remove();
			}
			if (options.footer) {
				$modal.find(".um-modal-footer").html(options.footer);
			} else {
				$modal.find(".um-modal-footer:empty").remove();
			}

			/* Content */
			let $modalBody = $modal.find(".um-modal-body");

			switch (typeof options.content) {
				case "function":
					let res = options.content.apply($modal, [event, options]);
					if (
						typeof res === "object" &&
						typeof res.done === "function" &&
						typeof res.fail === "function"
					) {
						// Action fired before loading modal content by AJAX
						wp.hooks.doAction("um-modal-before-ajax", $modal, options, res);
						this.loading(true, $modal);

						res
							.always(function() {
								UM.modal.loading(false, $modal);
							})
							.done(function(data) {
								if (
									typeof data === "string" &&
									$modal.find(".um-modal-body").children().length === 0
								) {
									UM.modal.setContent(data, $modal);
								}

								// Action fired if modal content AJAX request is successful
								wp.hooks.doAction("um-modal-after-ajax", $modal, data, res);
							})
							.fail(function(data) {
								console.error(data);

								// Action fired if modal content AJAX request is failed
								wp.hooks.doAction(
									"um-modal-after-ajax-fail",
									$modal,
									data,
									res
								);
							});
					} else {
						this.setContent(res, $modal);
					}
					break;

				case "object":
					this.setContent($(options.content).clone(), $modal);
					break;

				case "string":
					if (options.content === "loading") {
						this.loading(true, $modal);
					} else if (/^https?:/.test(options.content)) {
						this.loading(true, $modal);
						$modalBody.load(options.content.trim(), function() {
							this.loading(false, $modal);
							UM.modal.responsive($modal);
						});
					} else if (
						/^(#|\.)/.test(options.content) &&
						$(options.content).length
					) {
						this.setContent(
							$(options.content)
								.clone()
								.children(),
							$modal
						);
					} else {
						this.setContent(options.content, $modal);
					}
					break;

				default:
					this.setContent(options.content, $modal);
			}

			/* Attributes, classes and styles */
			$modal.get(0).umModalOptions = options;
			if (typeof options.attributes === "object") {
				for (let i in options.attributes) {
					$modal.attr(i, options.attributes[i]);
				}
			}
			if (options.classes.length) {
				$modal.addClass(options.classes);
			}
			if (options.size.length) {
				$modal.addClass(options.size);
			}

			/* Handlers */
			$modal.on("click", 'a:not([href^="javascript"])', this.stopEvent);
			$modal.on("touchmove", this.stopEvent);

			$modalBody.find("img").on("load", function() {
				UM.modal.responsive($modal);
			});

			// Action fired before modal is added.
			wp.hooks.doAction("um-modal-before-add", $modal, options);

			/* Add to the stack of modals and display */
			this.hide();
			this.M.push($modal);
			this.show($modal);

			return $modal;
		},

		/**
		 * Add and display a modal overlay
		 * @returns {object}  A modal overlay jQuery object.
		 */
		addOverlay: function() {
			if ($("body > .um-modal-overlay").length < 1) {
				$(document.body)
					.addClass("um-overflow-hidden")
					.append('<div class="um-modal-overlay"></div>')
					.on("touchmove", this.stopEvent);
			}
			return $("body > .um-modal-overlay");
		},

		/**
		 * Remove all modals and overlay
		 * @returns {ModalManagerUM}
		 */
		clear: function() {
			this.M = [];
			$(document.body)
				.removeClass("um-overflow-hidden")
				.off("touchmove")
				.children(".um-modal-overlay, .um-modal")
				.remove();

			if ($(document.body).css("overflow-y") === "hidden") {
				$(document.body).css("overflow-y", "visible");
			}
			return this;
		},

		/**
		 * Close the current modal
		 * @returns {ModalManagerUM}
		 */
		close: function() {
			let $modal = this.getModal();

			if ($modal && $modal.length) {
				// Action fired before the modal is closed
				wp.hooks.doAction("um-modal-before-close", $modal);
			}

			if (this.M.length > 1) {
				this.M.pop().remove();
				this.show();
			} else {
				this.clear();
			}

			return this;
		},

		/**
		 * Close all modals
		 * @returns {ModalManagerUM}
		 */
		closeAll: function() {
			let $modal = this.getModal();

			// trigger hook for currently visible modal
			if ($modal && $modal.length) {
				// Action fired before the modal is closed
				wp.hooks.doAction("um-modal-before-close", $modal);
			}

			this.clear();
			return this;
		},

		/**
		 * Filter modal options
		 * @param   {object} options  Modal options.
		 * @returns {object}          Modal options.
		 */
		filterOptions: function(options) {
			// Use this filter to modify default modal options
			let defOptions = wp.hooks.applyFilters(
				"um-modal-def-options",
				this.defaultOptions
			);

			return $.extend({}, defOptions, options);
		},

		/**
		 * Get the current modal
		 * @param   {object|string} modal  A modal element. Optional.
		 * @returns {object|null}          A modal jQuery object or NULL.
		 */
		getModal: function(modal) {
			let $modal;

			if (typeof modal === "object") {
				$modal = $(modal);
			} else if (typeof modal === "string" && this.M.length >= 1) {
				$.each(this.M, function(i, $m) {
					if ($m.is(modal)) {
						$modal = $m;
					}
				});
			} else if (this.M.length >= 1) {
				$modal = this.M[this.M.length - 1];
			} else {
				$modal = $("div.um-modal:not(.um-modal-hidden)").filter(":visible");
			}

			return $modal.length ? $modal.last() : null;
		},

		/**
		 * Hide the current modal
		 * @param   {object} modal  A modal element. Optional.
		 * @returns {object|null}   Hidden modal if exists.
		 */
		hide: function(modal) {
			let $modal = this.getModal(modal);
			if ($modal) {
				$modal.detach();

				// Action fired when modal is hidden
				wp.hooks.doAction("um-modal-hidden", $modal);
			}
			return $modal;
		},

		/**
		 * Add or remove loading icon
		 * @param   {Boolean} isLoading  The modal is awaiting a request.
		 * @param   {object} modal       A modal element. Optional.
		 * @returns {ModalManagerUM}
		 */
		loading: function(isLoading, modal) {
			let $modal = this.getModal(modal);

			if (isLoading) {
				$modal.addClass("loading");
				$modal.on("click", this.stopEvent);
			} else {
				$modal.removeClass("loading");
				$modal.off("click");
			}

			return this;
		},

		/**
		 * Update modal size and position
		 * @param   {object} modal   A modal element. Optional.
		 * @returns {ModalManagerUM}
		 */
		responsive: function(modal) {
			let $modal = this.getModal(modal);

			if ($modal) {
				$modal
					.removeClass("uimob340")
					.removeClass("uimob500")
					.removeClass("uimob800")
					.removeClass("uimob960");

				const modalHeightDiff = 30;
				let modalBodyHeightDiff =
					$modal.find(".um-modal-header").outerHeight() * 1;

				let w =
					window.innerWidth ||
					document.documentElement.clientWidth ||
					document.body.clientWidth;

				let h =
					window.innerHeight ||
					document.documentElement.clientHeight ||
					document.body.clientHeight;

				let $photo = $modal.find(".um-modal-body > img").filter(":visible"),
					modalStyle = {},
					modalBodyStyle = {};

				if ($photo.length) {
					$photo.css({
						maxHeight: h * 0.8,
						maxWidth: w * 0.8
					});

					modalStyle.bottom = (h - $modal.innerHeight()) / 2 + "px";
					modalStyle.marginLeft = "-" + $photo.width() / 2 + "px";
					modalStyle.width = $photo.width();
				} else if (w <= 340) {
					modalStyle.bottom = 0;
					modalStyle.height = h;
					modalStyle.width = w;
					$modal.addClass("uimob340");
				} else if (w <= 500) {
					modalStyle.bottom = 0;
					modalStyle.height = h;
					modalStyle.width = w;
					$modal.addClass("uimob500");
				} else if (w <= 800) {
					modalStyle.bottom = (h - $modal.innerHeight()) / 2 + "px";
					modalStyle.maxHeight = h - modalHeightDiff;
					modalBodyStyle.maxHeight = modalStyle.maxHeight - modalBodyHeightDiff;
					$modal.addClass("uimob800");
				} else if (w <= 960) {
					modalStyle.bottom = (h - $modal.innerHeight()) / 2 + "px";
					modalStyle.maxHeight = h - modalHeightDiff;
					modalBodyStyle.maxHeight = modalStyle.maxHeight - modalBodyHeightDiff;
					$modal.addClass("uimob960");
				} else if (w > 960) {
					modalStyle.bottom = (h - $modal.innerHeight()) / 2 + "px";
					modalStyle.maxHeight = h - modalHeightDiff;
					modalBodyStyle.maxHeight = modalStyle.maxHeight - modalBodyHeightDiff;
				}

				if ($modal.width() > w) {
					modalStyle.width = w * 0.9;
					modalStyle.marginLeft = "-" + modalStyle.width / 2 + "px";
				}

				// Use this filter to modify modal styles
				modalStyle = wp.hooks.applyFilters(
					"um-modal-responsive",
					modalStyle,
					$modal
				);

				$modal.css(modalStyle);
				$modal.find(".um-modal-body").css(modalBodyStyle);
			}

			return this;
		},

		/**
		 * Update a modal content
		 * @param   {string} content  A new content
		 * @param   {object} modal    A modal element. Optional.
		 * @returns {ModalManagerUM}
		 */
		setContent: function(content, modal) {
			let $modal = this.getModal(modal);

			if ($modal) {
				$modal.find(".um-modal-body").html(content);
				this.responsive($modal);

				// Action fired when modal content is inserted
				wp.hooks.doAction("um-modal-content-added", $modal);
			}

			return this;
		},

		/**
		 * Show the current modal
		 * @param   {object} modal  A modal element. Optional.
		 * @returns {object|null}   Shown modal if exists.
		 */
		show: function(modal) {
			let $modal = this.getModal(modal);
			if ($modal) {
				let options = $modal.get(0).umModalOptions;
				this.addOverlay().after($modal);
				this.responsive($modal);
				$modal.animate({ opacity: 1 }, options.duration);

				// Action fired when modal is shown
				wp.hooks.doAction("um-modal-shown", $modal);
			}
			return $modal;
		},

		/**
		 * Stop event propagation
		 * @param {object} event  jQuery event object.
		 */
		stopEvent: function(event) {
			event.preventDefault();
			event.stopPropagation();
		}
	};

	/* Global ModalManagerUM */
	if (typeof window.UM === "undefined") {
		window.UM = {};
	}
	UM.modal = new ModalManagerUM();

	/* event handlers */
	$(document.body).on("click", ".um-modal-overlay, .um-modal-close", function(
		e
	) {
		e.preventDefault();
		UM.modal.close();
	});

	$(window).on("load", function() {
		UM.modal.responsive();
	});

	$(window).on("resize", function() {
		UM.modal.responsive();
	});

	/**
	 * Add modal for the button
	 * @param   {object} options  Modal properties. Optional.
	 * {
			{object} attributes,
			{string} classes,
			{number} duration,
			{string} footer,
			{string} header,
			{string} size,
			{string} template,
			{function|object|string} content
		 }
	 * @returns {object}
	 */
	$.fn.umModal = function(options) {
		const settings = UM.modal.filterOptions(options);

		this.each(function(i, item) {
			let $button = $(item);

			if (!$button.data("um-modal-ready")) {
				$button.on("click", function(e) {
					e.preventDefault();
					settings.relatedButton = $button;

					// Action fired when the button is clicked
					wp.hooks.doAction("um-modal-button-clicked", settings, e);

					UM.modal.addModal(settings, e);
				});

				$button.data("um-modal-ready", true);
			}
		});

		return this;
	};
})(jQuery);
