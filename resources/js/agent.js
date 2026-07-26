import $ from 'jquery';
import select2 from 'select2';
import 'select2/dist/css/select2.css';
import '../scss/agent-select2.scss';

window.$ = window.jQuery = $;

// Select2's CommonJS build does NOT register itself — module.exports is a
// (root, jQuery) factory. Importing it alone leaves $.fn.select2 undefined, so it
// has to be invoked against our jQuery instance.
select2(window, $);

/**
 * Turn every <select> inside an opted-in form into a searchable Select2.
 * A form opts in with data-select2; individual selects opt out with data-no-select2.
 * Pass a container (e.g. a freshly added room row) to initialise just that subtree.
 */
window.initSelect2 = function (container) {
    // Filter by closest() rather than a descendant selector: when a container is passed
    // it IS the subtree (a room row), so `[data-select2] select` would match nothing.
    const selects = (container ? $(container).find('select') : $('select'))
        .filter(function () {
            return $(this).closest('[data-select2]').length > 0;
        });

    selects.each(function () {
        const el = $(this);

        if (el.data('select2') || el.is('[data-no-select2]')) {
            return;
        }

        // Select2 announces a selection with jQuery's own trigger('change'), which does
        // NOT reach listeners bound via addEventListener — and every booking-form
        // listener (fillPackage, recalc) is bound that way. Re-dispatch a real DOM event
        // so the existing vanilla code keeps working untouched.
        el.on('select2:select select2:clear', function () {
            this.dispatchEvent(new Event('change', { bubbles: true }));
        });

        el.select2({
            width: '100%',
            // The phone shell is a fixed-width frame, so the dropdown has to be
            // attached to the field's own card or it escapes the viewport.
            dropdownParent: el.closest('.card').length ? el.closest('.card') : $(document.body),
            // Search on every select, as specified — Select2 hides it by default on
            // short lists, so the threshold has to be forced to 0.
            minimumResultsForSearch: 0,
            placeholder: el.data('placeholder') || 'Choose…',
            allowClear: false,
        });
    });
};

$(function () {
    window.initSelect2();
});
