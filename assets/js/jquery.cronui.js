/**
 * @file jquery.cronui.js
 * @brief jQuery plugin to generate cron string
 * @author Imants Cernovs <imantscernovs@inbox.lv>
 * @version 1.0.2
 */

(function($) {

    var $select_box = [];
    var $settings;

    var defaults = {
        initial : '* * * * *',
        dropDownMultiple: false,
        dropDownStyled: false,
        dropDownStyledFlat: false,
        dropDownClass: 'form-control',
        dropDownSizeClass: 'col-md-2',
        resultOutputId: 'result_out',
        lang: 'en'
    };

    /** Cron combinations for detecting cron type */
    var combinations = {
        'minute' : /^(\*\s){4}\*$/,                               // '* * * * *'
        'hour'   : /^\d+(?:,\d+)*\s(\*\s){3}\*$/,                 // '? * * * *'
        'day'    : /^(\d+(?:,\d+)*\s+){2}(\*\s){2}\*$/,           // '? ? * * *'
        'week'   : /^(\d+(?:,\d+)*\s+){2}(\*\s){2}\d+(?:,\d+)*$/, // '? ? * * ?'
        'month'  : /^(\d+(?:,\d+)*\s+){3}(\*\s)\*$/,              // '? ? ? * *'
        'year'   : /^(\d+(?:,\d+)*\s+){4}\*$/                     // '? ? ? ? *'
    };

    /** Public plugin methods */
    var methods = {
        init : function(options) {

            /** Extend default settings */
            $settings = $.extend({}, defaults, options);

            /** Set default fallback language */
            $settings.lang = data.hasOwnProperty($settings.lang) ? $settings.lang : 'en';

            /** Init styled dropdowns */
            if ($settings.dropDownStyled) {
                if (typeof $.fn.selectpicker != typeof undefined) {

                    var $dropDownFlat = ($settings.dropDownStyledFlat) ? 'btn-flat' : '';

                    $(document).ready(function () {
                        $('.multiple-cron').selectpicker({
                            selectedTextFormat: 'count > 1',
                            style: $dropDownFlat + ' btn-light',
                            size: 12
                        });
                    });
                    $settings.dropDownClass = $settings.dropDownClass + ' multiple-cron';
                } else {
                    $.error('Cannot find bootstrap-select plugin. Please first load bootstrap-select plugin.')
                }
            }

            /** Generate inputs */
            $select_box['period'] =
                $('<div/>', {class: 'cron-period ' + $settings.dropDownSizeClass})
                    .append($('<label/>', {text: data[$settings.lang]['period']}))
                    .append($('<select/>', {id: 'period-box', class: 'cron-box cron-period-box ' + $settings.dropDownClass}))
                    .appendTo(this);

            $select_box['minute'] =
                $('<div/>', {class: 'cron-select cron-min ' + $settings.dropDownSizeClass})
                    .append($('<label/>', {text: data[$settings.lang]['minute']}))
                    .append($('<select/>', {id: 'min-box', class: 'cron-box cron-min-box ' + $settings.dropDownClass, multiple: $settings.dropDownMultiple}))
                    .appendTo(this);

            $select_box['hour'] =
                $('<div/>', {class: 'cron-select cron-hour ' + $settings.dropDownSizeClass})
                    .append($('<label/>', {text: data[$settings.lang]['hour']}))
                    .append($('<select/>', {id: 'hour-box', class: 'cron-box cron-hour-box ' + $settings.dropDownClass, multiple: $settings.dropDownMultiple}))
                    .appendTo(this);

            $select_box['month'] =
                $('<div/>', {class: 'cron-select cron-month ' + $settings.dropDownSizeClass})
                    .append($('<label/>', {text: data[$settings.lang]['month']}))
                    .append($('<select/>', {id: 'month-box', class: 'cron-box cron-month-box ' + $settings.dropDownClass, multiple: $settings.dropDownMultiple}))
                    .appendTo(this);

            $select_box['dom'] =
                $('<div/>', {class: 'cron-select cron-dom ' + $settings.dropDownSizeClass})
                    .append($('<label/>', {text: data[$settings.lang]['dom']}))
                    .append($('<select/>', {id: 'dom-box', class: 'cron-box cron-dom-box ' + $settings.dropDownClass, multiple: $settings.dropDownMultiple}))
                    .appendTo(this);

            $select_box['dow'] =
                $('<div/>', {class: 'cron-select cron-dow ' + $settings.dropDownSizeClass})
                    .append($('<label/>', {text: data[$settings.lang]['dow']}))
                    .append($('<select/>', {id: 'dow-box', class: 'cron-box cron-dow-box ' + $settings.dropDownClass, multiple: $settings.dropDownMultiple}))
                    .appendTo(this);

            /** Populate selects with data*/
            populateOptions($select_box['period'].find('select').attr('id'), generatePeriods());
            populateOptions($select_box['minute'].find('select').attr('id'), generateNumbers(60));
            populateOptions($select_box['hour'].find('select').attr('id'), generateNumbers(24));
            populateOptions($select_box['month'].find('select').attr('id'), generateData(1, 12, 'months'));
            populateOptions($select_box['dom'].find('select').attr('id'), generateMonthDays());
            populateOptions($select_box['dow'].find('select').attr('id'), generateData(0, 6, 'days'));

            /** Activate inputs based on choosen period */
            $('select.cron-period-box').change(function () {
                activateInputs(this.value);
                $('#' + $settings.resultOutputId).val(methods['getValue'].call());
            }).change();

            $('.cron-select select').change(function () {
                var $result = methods['getValue'].call();
                $('#' + $settings.resultOutputId).val($result);
            });

            methods['setValue'].call(this, $settings.initial);

            $('#' + $settings.resultOutputId).val(methods['getValue'].call());

            return this;

        },

        setValue : function(cron_str) {

            /** Get cron type based on given string */
            var cron_type = getCronType(cron_str);
            $select_box['period'].find('select').val(cron_type);

            /** Activate inputs based on detected period */
            activateInputs(cron_type);

            var cron = cron_str.split(' ');
            var $values = {
                'minute' : cron[0],
                'hour'   : cron[1],
                'dom'    : cron[2],
                'month'  : cron[3],
                'dow'    : cron[4]
            };

            $.each($values, function (value, index) {

                /** Skip asterisk */
                if (index == '*') return;

                /** Set single cron value */
                $select_box[value].find('select').val(index);

                /** Set multiple cron value if cron string contains comma */
                if (index.includes(',')) {
                    var $multiple = index.split(',');
                    $select_box[value].find('select').val($multiple);
                }

            });

            $('#' + $settings.resultOutputId).val(methods['getValue'].call());

        },

        getValue : function () {

            var min, hour, day, month, dow; min = hour = day = month = dow = '*';

            var $period    = $select_box['period'].find('select').val();
            var $min_val   = $select_box['minute'].find('select').val();
            var $hour_val  = $select_box['hour'].find('select').val();
            var $day_val   = $select_box['dom'].find('select').val();
            var $month_val = $select_box['month'].find('select').val();
            var $dow_val   = $select_box['dow'].find('select').val();

            switch ($period) {
                case 'hour':
                    min = ($min_val != null) ? $min_val : '0';
                break;
                case 'day':
                    min  = ($min_val != null)  ? $min_val  : '0';
                    hour = ($hour_val != null) ? $hour_val : '0';
                break;
                case 'week':
                    min  = ($min_val != null)  ? $min_val  : '0';
                    hour = ($hour_val != null) ? $hour_val : '0';
                    dow  = ($dow_val != null)  ? $dow_val  : '0';
                break;
                case 'month':
                    min  = ($min_val != null)  ? $min_val  : '0';
                    hour = ($hour_val != null) ? $hour_val : '0';
                    day  = ($day_val != null)  ? $day_val  : '1';
                break;
                case 'year':
                    min   = ($min_val != null)   ? $min_val   : '0';
                    hour  = ($hour_val != null)  ? $hour_val  : '0';
                    day   = ($day_val != null)   ? $day_val   : '1';
                    month = ($month_val != null) ? $month_val : '1';
                break;
            }

            return [min, hour, day, month, dow].join(' ');
        }

    };

    /**
     * Determine cron type based on cron string
     *
     * @param  {string} cron_str
     * @return {string}
     */
    var getCronType = function (cron_str) {

        /** validate cron string */
        validateCronString(cron_str);

        /** Determine cron type */
        for (var type in combinations) {
            if (combinations[type].test(cron_str)) { return type; }
        }

    };

    /**
     * Populate select boxes with options
     *
     * @param {object} $element
     * @param {Array} $options
     */
    var populateOptions = function ($element, $options) {
        $.each($options, function() {
            $('#' + $element).append($('<option />').val(this.val).text(this.text));
        });
    };

    /**
     * Generate number sequence
     *
     * @param  {int}$length
     * @return {Array}
     */
    var generateNumbers = function ($length) {
        return $.map($(new Array($length)),function(val, n) {
            var $text = (n > 9) ? n.toString() : '0' + n;
            return {val: n, text: $text};
        });
    };

    /**
     * Generate month days
     *
     * @return {Array}
     */
    var generateMonthDays = function () {
        var $dim = [];
        for (var $i = 1; $i <= 31; $i++) {
            $dim.push({val: $i, text: $i});
        }
        return $dim;
    };

    /**
     * Generate translated data
     *
     * @param  {int} $start
     * @param  {int} $end
     * @param  {string} $element
     * @return {Array}
     */
    var generateData = function ($start, $end, $element) {
        var $data_array = [];

        try {
            var $items = data[$settings.lang][$element];

            for (var $i = $start; $i <= $end; $i++) {
                $data_array.push({val: $i, text: $items[$i - $start]})
            }

        } catch (e) {
            console.error('Translation for ' + $element +' does not exists in cronui-' + $settings.lang);
        }

        return $data_array;
    };

    /**
     * Generate translated periods
     *
     * @return {Array}
     */
    var generatePeriods = function () {
        var $data_array = [];
        var $values     = ['minute', 'hour', 'day', 'week', 'month', 'year'];

        try {
            var $items = data[$settings.lang]['periods'];
            $.each($values, function (index, value) {
                $data_array.push({val: value, text: $items[index]});
            })
        } catch (e) {
            console.error('Translation for periods does not exists in cronui-' + $settings.lang);
        }

        return $data_array;
    };

    /**
     * Validate cron string correctness
     *
     * @param cron_str
     * @return {boolean}
     */
    var validateCronString = function (cron_str) {

        var cron       = cron_str.split(' ');
        var valid_cron = /^((\d+(?:,\d+)*|\*)\s){4}(\d+(?:,\d+)*|\*)$/;
        var minval     = [ 0,  0,  1,  1,  0]; // mm, hh, DD, MM, DOW
        var maxval     = [59, 23, 31, 12,  6]; // mm, hh, DD, MM, DOW

        var $error = '';
        var $valid = true;

        /** Check format of initial cron value */
        if (typeof cron_str != 'string' || !valid_cron.test(cron_str)) {
            $error += 'Invalid cron string: ' + cron_str + '\n';
            $valid = false;
        } else {
            /** check actual cron values */
            $.each(cron, function (index, str) {
                if (str == '*') return;

                var $cron_val = (str.includes(',')) ? str.split(',') : parseInt(str);

                if ($.isArray($cron_val)) {
                    $.each($cron_val, function (i, cr_str) {
                        var $ar_cron_val = parseInt(cr_str);
                        if ($ar_cron_val <= maxval[index] && $ar_cron_val >= minval[index]) return;
                        $error += 'Invalid value found column: ' + (index + 1 ) + ' value: ' + ($cron_val[i]) + '\n';
                        $valid = false;
                    });
                } else {
                    if ($cron_val <= maxval[index] && $cron_val >= minval[index]) return;
                    $error += 'Invalid value found column: ' + (index + 1 ) + ' value: ' + (cron[index]);
                    $valid = false;
                }
            });
        }

        /** Show error message in alert box */
        if ($valid == false) {
            alert($error);
            return false;
        }

        return true;

    };

    /**
     * Activate inputs based on selected period
     *
     * @param {string} $period
     */
    var activateInputs = function ($period) {

        var $cron_select = $('.cron-select');
        $cron_select.find('select').attr('disabled', true);

         var $min_box   = $select_box['minute'].find('select');
         var $hour_box  = $select_box['hour'].find('select');
         var $dom_box   = $select_box['dom'].find('select');
         var $month_box = $select_box['month'].find('select');
         var $dow_box   = $select_box['dow'].find('select');

         switch ($period) {
             case 'hour':
                 $min_box.prop('disabled', false);
             break;
             case 'day':
                 $min_box.prop('disabled', false);
                 $hour_box.prop('disabled', false);
             break;
             case 'week':
                 $min_box.prop('disabled', false);
                 $hour_box.prop('disabled', false);
                 $dow_box.prop('disabled', false);
             break;
             case 'month':
                 $min_box.prop('disabled', false);
                 $hour_box.prop('disabled', false);
                 $dom_box.prop('disabled', false);
             break;
             case 'year':
                 $min_box.prop('disabled', false);
                 $hour_box.prop('disabled', false);
                 $dom_box.prop('disabled', false);
                 $month_box.prop('disabled', false);
             break;
         }

        $(document).ready(function () {
            if ($settings.dropDownStyled) {
                $cron_select.find('select').selectpicker('refresh');
            }
        });

    };

    $.fn.cronui = function(method) {
        if (methods[method]) {
            return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
        } else if (typeof method === 'object' || ! method) {
            return methods.init.apply(this, arguments);
        } else {
            $.error( 'Method ' +  method + ' does not exist' );
        }
    };

    /** Default plugin text */
    var data = $.fn.cronui.data = {
        'en': {
            periods: ['Minute', 'Hour', 'Day', 'Week', 'Month', 'Year'],
            days: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'Novermber', 'December'],
            period: 'Every',
            minute: 'Minute',
            hour: 'Hour',
            month: 'Month',
            dom: 'Day of month',
            dow: 'Day of week'
        }
    };

})(jQuery);
