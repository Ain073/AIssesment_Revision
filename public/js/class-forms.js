(() => {
    const sectionNameOptions = (prefix, yearLevel) => {
        const cleanPrefix = (prefix || 'Section').trim();
        const cleanYear = (yearLevel || '').trim();
        const letters = ['A', 'B', 'C', 'D'];

        return letters.map((letter) => cleanYear ? `${cleanPrefix} ${cleanYear}-${letter}` : `${cleanPrefix} ${letter}`);
    };

    const syncClassSectionSelect = (sectionSelect) => {
        const form = sectionSelect.closest('form');
        const programSelect = form?.querySelector('select[name="program_id"]');
        const yearSelect = form?.querySelector('select[name="year_level"]');
        const hasProgram = Boolean(programSelect?.value);
        const selectedProgram = programSelect?.selectedOptions?.[0];
        const prefix = selectedProgram?.dataset.sectionPrefix || 'Section';
        const selectedValue = sectionSelect.value || sectionSelect.dataset.selectedSection || '';
        const options = sectionNameOptions(prefix, yearSelect?.value || '');

        sectionSelect.innerHTML = hasProgram
            ? '<option value="">Select section</option>'
            : '<option value="">Select program first</option>';

        if (! hasProgram) {
            return;
        }

        if (selectedValue && ! options.includes(selectedValue)) {
            options.unshift(selectedValue);
        }

        options.forEach((optionValue) => {
            const option = document.createElement('option');
            option.value = optionValue;
            option.textContent = optionValue;
            option.selected = optionValue === selectedValue;
            sectionSelect.append(option);
        });
    };

    window.initializeClassSectionSelects = (root = document) => {
        const selects = root.matches?.('[data-class-section-select]')
            ? [root]
            : Array.from(root.querySelectorAll('[data-class-section-select]'));

        selects.forEach(syncClassSectionSelect);
    };

    const syncClassFormAvailability = (form, resetValues = false) => {
        if (! form) {
            return;
        }

        const yearSelect = form.querySelector('select[name="year_level"]');
        const sectionSelect = form.querySelector('[data-class-section-select]');
        const schoolYearPair = form.querySelector('[data-school-year-pair]');
        const schoolYearInputs = schoolYearPair ? Array.from(schoolYearPair.querySelectorAll('input')) : [];

        if (yearSelect) {
            yearSelect.disabled = false;
        }

        if (sectionSelect) {
            sectionSelect.disabled = false;
            syncClassSectionSelect(sectionSelect);
        }

        schoolYearPair?.classList.remove('is-disabled');
        schoolYearInputs.forEach((input) => {
            input.disabled = false;
        });
    };

    window.initializeClassForms = (root = document) => {
        const forms = root.matches?.('form')
            ? [root]
            : Array.from(root.querySelectorAll('form'));

        forms
            .filter((form) => form.querySelector('[data-class-section-select]'))
            .forEach((form) => syncClassFormAvailability(form));
    };

    const cleanTwoDigitYear = (input) => {
        input.value = input.value.replace(/\D/g, '').slice(0, 2);
    };

    const syncSchoolYearEnd = (startInput) => {
        cleanTwoDigitYear(startInput);

        if (startInput.value.length !== 2) {
            return;
        }

        const pair = startInput.closest('[data-school-year-pair]');
        const endInput = pair?.querySelector('input[name="school_year_end"]');
        const nextYear = (Number(startInput.value) + 1) % 100;

        if (endInput) {
            endInput.value = String(nextYear).padStart(2, '0');
        }
    };

    document.addEventListener('input', (event) => {
        if (event.target.matches('input[name="school_year_start"]')) {
            syncSchoolYearEnd(event.target);
        }

        if (event.target.matches('input[name="school_year_end"]')) {
            cleanTwoDigitYear(event.target);
        }
    });

    document.addEventListener('change', (event) => {
        const field = event.target.closest('select[name="subject_id"], select[name="program_id"], select[name="year_level"]');

        if (! field) {
            return;
        }

        const form = field.closest('form');

        form?.querySelectorAll('[data-class-section-select]').forEach((sectionSelect) => {
            sectionSelect.dataset.selectedSection = '';
            sectionSelect.value = '';
        });

        syncClassFormAvailability(form, field.name === 'subject_id' && ! field.value);
    });
})();
