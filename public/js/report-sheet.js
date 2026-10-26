(() => {
    const configNode = document.getElementById('reportSheetConfig');
    if (! configNode) return;
    const settings = JSON.parse(configNode.textContent);
    const itemAnalysisQuiz = document.getElementById('reportItemAnalysisQuiz');
    itemAnalysisQuiz?.addEventListener('change', () => {
        document.querySelectorAll('[data-item-analysis-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.itemAnalysisPanel !== itemAnalysisQuiz.value;
        });
        document.querySelector('#reportItemAnalysisModal .modal-body')?.scrollTo(0, 0);
    });

    const paperOptions = settings.paperOptions;
    const paperSize = document.getElementById('paperSize');
    const paperSizeInput = document.getElementById('paperSizeInput');
    const paperSizeCaption = document.getElementById('paperSizeCaption');
    const reportContentFontFamily = document.getElementById('reportContentFontFamily');
    const reportContentFontSize = document.getElementById('reportContentFontSize');
    const reportForm = document.getElementById('reportSheetForm');
    const reportLogoForm = document.getElementById('reportLogoForm');
    const editReportLogoButton = document.getElementById('editReportLogoButton');
    const reportLogoInput = document.getElementById('reportLogoInput');
    const reportLogoPreview = document.getElementById('reportLogoPreview');
    const draftFieldSelector = '.report-edit-textarea, .report-column-input, .report-header-input:not([data-report-generated])';
    const contentFontFamilies = {
        arial: 'Arial, Helvetica, sans-serif',
        calibri: 'Calibri, Arial, sans-serif',
        times: '"Times New Roman", Times, serif',
    };
    const contentFontStorageKey = `aissessment:report-content-style:${settings.reportType}`;

    const reportDraftFields = () => reportForm
        ? [...reportForm.querySelectorAll(draftFieldSelector)]
        : [];

    const applyReportContentStyle = () => {
        const fontKey = reportContentFontFamily?.value || 'arial';
        const fontSize = reportContentFontSize?.value || '9';

        document.documentElement.style.setProperty(
            '--report-content-font-family',
            contentFontFamilies[fontKey] || contentFontFamilies.arial
        );
        document.documentElement.style.setProperty('--report-content-font-size', `${fontSize}pt`);

        try {
            localStorage.setItem(contentFontStorageKey, JSON.stringify({
                fontFamily: fontKey,
                fontSize,
            }));
        } catch (error) {
            // Ignore storage failures.
        }

        document.querySelectorAll('.report-edit-textarea').forEach((textarea) => {
            textarea.style.height = 'auto';
            textarea.style.height = `${Math.max(textarea.scrollHeight, 136)}px`;
        });
    };

    const restoreReportContentStyle = () => {
        try {
            const savedStyle = JSON.parse(localStorage.getItem(contentFontStorageKey));

            if (savedStyle?.fontFamily && reportContentFontFamily) {
                reportContentFontFamily.value = savedStyle.fontFamily;
            }

            if (savedStyle?.fontSize && reportContentFontSize) {
                reportContentFontSize.value = savedStyle.fontSize;
            }
        } catch (error) {
            // Keep defaults when style storage is unavailable.
        }

        applyReportContentStyle();
    };

    const reportAutosave = window.createReportAutosave?.(reportForm);
    const reportSheetWrap = document.querySelector('.report-sheet-wrap');
    const editReportButton = document.getElementById('editReportButton');
    const cancelEditButton = document.getElementById('cancelEditButton');
    const saveDraftButton = document.getElementById('saveDraftButton');
    const finalizeButton = document.getElementById('finalizeButton');
    const aiDraftButton = document.getElementById('aiDraftButton');
    const aiDraftLabel = aiDraftButton?.querySelector('[data-ai-draft-label]');
    const reportStatusBadge = document.getElementById('reportStatusBadge');
    const reportStatusBadgeIcon = document.getElementById('reportStatusBadgeIcon');
    const reportStatusBadgeText = document.getElementById('reportStatusBadgeText');
    const reportLockedNotice = document.getElementById('reportLockedNotice');

    let initialValuesSnapshot = {};

    const takeSnapshot = () => {
        initialValuesSnapshot = {};
        reportDraftFields().forEach((field) => {
            if (field.name) {
                initialValuesSnapshot[field.name] = field.value;
            }
        });
    };

    const toggleElementVisibility = (element, show) => {
        if (! element) return;
        if (show) {
            element.classList.remove('d-none');
        } else {
            element.classList.add('d-none');
        }
    };

    const setReportLocked = (locked) => {
        reportDraftFields().forEach((field) => {
            if (locked) {
                field.setAttribute('readonly', 'readonly');
                field.setAttribute('tabindex', '-1');
            } else {
                field.removeAttribute('readonly');
                field.removeAttribute('tabindex');
            }
        });

        if (locked) {
            toggleElementVisibility(editReportLogoButton, false);
            reportSheetWrap?.classList.add('is-locked');
            toggleElementVisibility(editReportButton, true);
            toggleElementVisibility(cancelEditButton, false);
            toggleElementVisibility(saveDraftButton, false);
            toggleElementVisibility(finalizeButton, false);
            toggleElementVisibility(aiDraftButton, false);
            toggleElementVisibility(reportLockedNotice, true);

            if (reportStatusBadge) {
                reportStatusBadge.className = 'report-status-badge badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1 px-2.5 py-1 fs-7 fw-bold';
                if (reportStatusBadgeIcon) reportStatusBadgeIcon.textContent = 'lock';
                if (reportStatusBadgeText) reportStatusBadgeText.textContent = 'Finalized';
            }
        } else {
            toggleElementVisibility(editReportLogoButton, true);
            reportSheetWrap?.classList.remove('is-locked');
            toggleElementVisibility(editReportButton, false);
            toggleElementVisibility(cancelEditButton, true);
            toggleElementVisibility(saveDraftButton, true);
            toggleElementVisibility(finalizeButton, true);
            toggleElementVisibility(aiDraftButton, true);
            toggleElementVisibility(reportLockedNotice, false);

            if (reportStatusBadge) {
                reportStatusBadge.className = 'report-status-badge badge bg-warning-subtle text-warning-emphasis border border-warning-subtle d-inline-flex align-items-center gap-1 px-2.5 py-1 fs-7 fw-bold';
                if (reportStatusBadgeIcon) reportStatusBadgeIcon.textContent = 'edit';
                if (reportStatusBadgeText) reportStatusBadgeText.textContent = 'Editing';
            }
        }
    };

    let logoPreviewUrl;
    const resetLogoSelection = () => {
        reportLogoForm?.reset();
        if (logoPreviewUrl) URL.revokeObjectURL(logoPreviewUrl);
        logoPreviewUrl = null;
        if (reportLogoPreview) reportLogoPreview.src = reportLogoPreview.dataset.currentSrc;
    };
    reportLogoInput?.addEventListener('change', () => {
        if (logoPreviewUrl) URL.revokeObjectURL(logoPreviewUrl);
        logoPreviewUrl = reportLogoInput.files?.[0] ? URL.createObjectURL(reportLogoInput.files[0]) : null;
        if (reportLogoPreview) reportLogoPreview.src = logoPreviewUrl || reportLogoPreview.dataset.currentSrc;
    });
    reportLogoForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (await window.beforeReportNavigation?.() !== false) reportLogoForm.submit();
    });
    document.getElementById('reportLogoModal')?.addEventListener('hidden.bs.modal', resetLogoSelection);
    if (settings.logoUploadError) {
        window.bootstrap?.Modal?.getOrCreateInstance(document.getElementById('reportLogoModal'))?.show();
    }

    if (editReportButton) {
        editReportButton.addEventListener('click', () => {
            takeSnapshot();
            setReportLocked(false);
            const firstField = document.querySelector(draftFieldSelector);
            firstField?.focus();
        });
    }

    if (cancelEditButton) {
        cancelEditButton.addEventListener('click', () => {
            reportDraftFields().forEach((field) => {
                if (field.name && Object.prototype.hasOwnProperty.call(initialValuesSnapshot, field.name)) {
                    field.value = initialValuesSnapshot[field.name];
                    syncPrintText(field);
                    if (field.classList.contains('report-edit-textarea')) {
                        autosize(field);
                    }
                }
            });
            clearReportDraft();
            resetLogoSelection();
            setReportLocked(true);
        });
    }

    const queueReportDraftSave = () => reportAutosave?.changed();
    const saveReportDraft = queueReportDraftSave;
    const clearReportDraft = () => reportAutosave?.clearLocal();

    paperSize?.addEventListener('change', () => {
        const url = new URL(window.location.href);
        url.searchParams.set('paper', paperSize.value);
        paperSizeInput.value = paperSize.value;
        paperSizeCaption.textContent = paperOptions[paperSize.value] ?? '';
        window.location.href = url.toString();
    });

    reportContentFontFamily?.addEventListener('change', applyReportContentStyle);
    reportContentFontSize?.addEventListener('change', applyReportContentStyle);

    const syncPrintText = (field) => {
        const printText = field.nextElementSibling;

        if (printText?.classList.contains('report-print-text')) {
            printText.textContent = field.value;
        }

        if (field.name) {
            document
                .querySelectorAll(`.report-print-mirror[data-print-field-name="${CSS.escape(field.name)}"]`)
                .forEach((printMirror) => {
                    printMirror.textContent = field.name === 'campus' ? field.value.toUpperCase() : field.value;
                });
        }
    };

    const autosize = (textarea) => {
        textarea.style.height = 'auto';
        textarea.style.height = `${Math.max(textarea.scrollHeight, 136)}px`;
        syncPrintText(textarea);
    };

    restoreReportContentStyle();

    document.querySelectorAll('.report-edit-textarea').forEach((textarea) => {
        autosize(textarea);
        textarea.addEventListener('input', () => {
            autosize(textarea);
            queueReportDraftSave();
        });
        textarea.addEventListener('change', () => {
            autosize(textarea);
            queueReportDraftSave();
        });
    });

    document.querySelectorAll('.report-column-input').forEach((input) => {
        input.addEventListener('input', () => {
            syncPrintText(input);
            queueReportDraftSave();
        });
        input.addEventListener('change', () => {
            syncPrintText(input);
            queueReportDraftSave();
        });
    });

    document.querySelectorAll('.report-header-input').forEach((input) => {
        syncPrintText(input);
        input.addEventListener('input', () => {
            syncPrintText(input);
            queueReportDraftSave();
        });
        input.addEventListener('change', () => {
            syncPrintText(input);
            queueReportDraftSave();
        });
    });

    const isFirefox = typeof InstallTrigger !== 'undefined' || navigator.userAgent.toLowerCase().includes('firefox');

    window.addEventListener('beforeprint', () => {
        applyReportContentStyle();
        document.querySelectorAll('.report-edit-textarea').forEach(autosize);
        reportDraftFields().forEach(syncPrintText);

        if (isFirefox) {
            document.documentElement.setAttribute('data-ff-print', '1');
        }
    });

    window.addEventListener('afterprint', () => {
        document.documentElement.removeAttribute('data-ff-print');
    });

    window.addEventListener('load', () => {
        applyReportContentStyle();
        document.querySelectorAll('.report-edit-textarea').forEach(autosize);
    });

    const aiButton = document.getElementById('generateAiDraftsButton');
    const aiStatus = document.getElementById('reportAiStatus');
    const aiConfirmModal = document.getElementById('confirmAiDraftModal');


    const showAiStatus = (message, type = 'info') => {
        if (! aiStatus) {
            return;
        }

        aiStatus.className = `alert alert-${type} report-ai-status show`;
        aiStatus.textContent = message;
    };

    const fillAiDrafts = (drafts) => {
        Object.entries(drafts).forEach(([assessmentKey, draft]) => {
            Object.entries(draft).forEach(([field, value]) => {
                const textarea = document.querySelector(`[data-assessment-key="${assessmentKey}"][data-ai-field="${field}"]`);

                if (textarea && value && field !== 'source') {
                    textarea.value = value;
                    autosize(textarea);
                }
            });
        });
    };

    aiButton?.addEventListener('click', async () => {
        const publishAssessmentKeys = [...reportForm.querySelectorAll('input[name="publish_assessment_keys[]"]')]
            .map((input) => input.value.trim())
            .filter(Boolean);

        if (publishAssessmentKeys.length === 0) {
            showAiStatus('No completed assessments were selected.', 'warning');
            return;
        }

        aiButton.disabled = true;
        if (aiDraftButton) {
            aiDraftButton.disabled = true;
            aiDraftButton.setAttribute('aria-busy', 'true');
        }
        if (aiDraftLabel) aiDraftLabel.textContent = 'Generating...';
        window.bootstrap?.Modal?.getInstance(aiConfirmModal)?.hide();
        showAiStatus('Generating AI draft content...', 'info');

        try {
            const response = await (window.portalFetch || fetch)(aiButton.dataset.aiUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken,
                },
                body: JSON.stringify({
                    report_type: settings.reportType,
                    publish_assessment_keys: publishAssessmentKeys,
                }),
            });

            const data = await response.json();

            if (! response.ok) {
                throw new Error(data.message ?? 'AI draft request failed.');
            }

            fillAiDrafts(data.drafts ?? {});
            saveReportDraft();

            showAiStatus('AI draft generated. Review and edit the text before finalizing your report.', 'success');
        } catch (error) {
            showAiStatus(error.message || 'Unable to generate AI draft. Please try again.', 'danger');
        } finally {
            aiButton.disabled = false;
            if (aiDraftButton) {
                aiDraftButton.disabled = false;
                aiDraftButton.setAttribute('aria-busy', 'false');
            }
            if (aiDraftLabel) aiDraftLabel.textContent = 'Generate AI Draft';
        }
    });
})();
