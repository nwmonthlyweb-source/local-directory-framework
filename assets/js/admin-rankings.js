(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var stateSelect = document.getElementById('nwmd_state_term_id');
        var citySelect = document.getElementById('nwmd_city_term_id');
        var categorySelect = document.getElementById(
            'nwmd_category_term_id'
        );
        var specialtySelect = document.getElementById(
            'nwmd_specialty_term_id'
        );
        var businessSelect = document.getElementById(
            'nwmd_business_post_id'
        );

        if (
            !stateSelect ||
            !citySelect ||
            !categorySelect ||
            !specialtySelect ||
            !businessSelect
        ) {
            return;
        }

        var cityOptions = cloneOptions(citySelect);
        var specialtyOptions = cloneOptions(specialtySelect);
        var businessOptions = cloneOptions(businessSelect);

        function cloneOptions(select) {
            return Array.prototype.map.call(
                select.options,
                function (option) {
                    return option.cloneNode(true);
                }
            );
        }

        function optionListContainsValue(options, value) {
            return options.some(function (option) {
                return option.value === value;
            });
        }

        function replaceOptions(select, options, preferredValue) {
            select.replaceChildren();

            options.forEach(function (option) {
                select.appendChild(option.cloneNode(true));
            });

            if (optionListContainsValue(options, preferredValue)) {
                select.value = preferredValue;
                return;
            }

            select.value = '';
        }

        function optionHasTerm(option, attribute, termId) {
            var rawTermIds = option.getAttribute(attribute) || '';

            if ('' === rawTermIds || '' === termId) {
                return false;
            }

            return rawTermIds.split(',').indexOf(termId) !== -1;
        }

        function refreshCities(preserveSelection) {
            var stateTermId = stateSelect.value;
            var preferredValue = preserveSelection
                ? citySelect.value
                : '';
            var availableOptions = cityOptions.filter(function (option) {
                return (
                    '' === option.value ||
                    (
                        '' !== stateTermId &&
                        option.getAttribute('data-state-term-id')
                            === stateTermId
                    )
                );
            });

            replaceOptions(
                citySelect,
                availableOptions,
                preferredValue
            );

            citySelect.disabled = '' === stateTermId;
        }

        function refreshSpecialties(preserveSelection) {
            var categoryTermId = categorySelect.value;
            var preferredValue = preserveSelection
                ? specialtySelect.value
                : '0';
            var availableOptions = specialtyOptions.filter(
                function (option) {
                    return (
                        '0' === option.value ||
                        (
                            '' !== categoryTermId &&
                            option.getAttribute(
                                'data-category-term-id'
                            ) === categoryTermId
                        )
                    );
                }
            );

            replaceOptions(
                specialtySelect,
                availableOptions,
                preferredValue
            );

            specialtySelect.disabled = '' === categoryTermId;
        }

        function refreshBusinesses(preserveSelection) {
            var stateTermId = stateSelect.value;
            var cityTermId = citySelect.value;
            var categoryTermId = categorySelect.value;
            var specialtyTermId = specialtySelect.value;
            var preferredValue = preserveSelection
                ? businessSelect.value
                : '';
            var prerequisitesSelected = (
                '' !== stateTermId &&
                '' !== cityTermId &&
                '' !== categoryTermId
            );
            var placeholderOptions = businessOptions.filter(
                function (option) {
                    return '' === option.value;
                }
            );

            if (!prerequisitesSelected) {
                replaceOptions(
                    businessSelect,
                    placeholderOptions,
                    ''
                );
                businessSelect.disabled = true;
                return;
            }

            var matchingOptions = businessOptions.filter(
                function (option) {
                    if ('' === option.value) {
                        return false;
                    }

                    return (
                        optionHasTerm(
                            option,
                            'data-state-term-ids',
                            stateTermId
                        ) &&
                        optionHasTerm(
                            option,
                            'data-city-term-ids',
                            cityTermId
                        ) &&
                        optionHasTerm(
                            option,
                            'data-category-term-ids',
                            categoryTermId
                        ) &&
                        (
                            '0' === specialtyTermId ||
                            optionHasTerm(
                                option,
                                'data-specialty-term-ids',
                                specialtyTermId
                            )
                        )
                    );
                }
            );

            if (0 === matchingOptions.length) {
                var noMatchesOption = document.createElement('option');

                noMatchesOption.value = '';
                noMatchesOption.textContent = businessSelect.getAttribute(
                    'data-no-matches-label'
                );
                noMatchesOption.disabled = true;
                noMatchesOption.selected = true;

                businessSelect.replaceChildren(noMatchesOption);
                businessSelect.disabled = true;
                return;
            }

            replaceOptions(
                businessSelect,
                placeholderOptions.concat(matchingOptions),
                preferredValue
            );
            businessSelect.disabled = false;
        }

        stateSelect.addEventListener('change', function () {
            refreshCities(false);
            refreshBusinesses(false);
        });

        citySelect.addEventListener('change', function () {
            refreshBusinesses(false);
        });

        categorySelect.addEventListener('change', function () {
            refreshSpecialties(false);
            refreshBusinesses(false);
        });

        specialtySelect.addEventListener('change', function () {
            refreshBusinesses(false);
        });

        refreshCities(true);
        refreshSpecialties(true);
        refreshBusinesses(true);
    });
}());
