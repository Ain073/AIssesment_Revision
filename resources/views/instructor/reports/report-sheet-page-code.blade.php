<script>
    (() => {
        const paperOptions = @json(collect($paperOptions)->map(fn ($option) => $option['description']));
        const paperSize = document.getElementById('paperSize');
        const paperSizeInput = document.getElementById('paperSizeInput');
        const paperSizeCaption = document.getElementById('paperSizeCaption');
        const reportContentFontFamily = document.getElementById('reportContentFontFamily');
        const reportContentFontSize = document.getElementById('reportContentFontSize');
        const reportForm = document.getElementById('reportSheetForm');
        const draftFieldSelector = '.report-edit-textarea, .report-header-input';
        const contentFontFamilies = {
            arial: 'Arial, Helvetica, sans-serif',
            calibri: 'Calibri, Arial, sans-serif',
            times: '"Times New Roman", Times, serif',
        };
        const contentFontStorageKey = `aissessment:report-content-style:${@json($reportType)}`;
        const reportDraftAssessmentKeys = reportForm
            ? [...reportForm.querySelectorAll('input[name="class_assessment_keys[]"]')]
                .map((input) => input.value.trim())
                .filter(Boolean)
                .sort()
                .join('|')
            : '';
        const reportDraftStorageKey = reportDraftAssessmentKeys
            ? `aissessment:report-draft:${@json($reportType)}:${reportDraftAssessmentKeys}`
            : null;
        let reportDraftSaveTimer = null;
        let reportFormSubmitting = false;

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

        const saveReportDraft = () => {
            if (! reportDraftStorageKey) {
                return;
            }

            const values = {};
            reportDraftFields().forEach((field) => {
                if (field.name) {
                    values[field.name] = field.value;
                }
            });

            try {
                localStorage.setItem(reportDraftStorageKey, JSON.stringify({
                    savedAt: Date.now(),
                    values,
                }));
            } catch (error) {
                // Ignore storage failures so report editing remains usable.
            }
        };

        const queueReportDraftSave = () => {
            clearTimeout(reportDraftSaveTimer);
            reportDraftSaveTimer = setTimeout(saveReportDraft, 150);
        };

        const clearReportDraft = () => {
            if (! reportDraftStorageKey) {
                return;
            }

            try {
                localStorage.removeItem(reportDraftStorageKey);
            } catch (error) {
                // Ignore storage failures.
            }
        };

        const restoreReportDraft = () => {
            if (! reportDraftStorageKey) {
                return;
            }

            try {
                const savedDraft = JSON.parse(localStorage.getItem(reportDraftStorageKey));
                const maxDraftAge = 7 * 24 * 60 * 60 * 1000;

                if (! savedDraft?.values || Date.now() - Number(savedDraft.savedAt ?? 0) > maxDraftAge) {
                    clearReportDraft();
                    return;
                }

                reportDraftFields().forEach((field) => {
                    if (field.name && Object.prototype.hasOwnProperty.call(savedDraft.values, field.name)) {
                        field.value = savedDraft.values[field.name];
                    }
                });
            } catch (error) {
                clearReportDraft();
            }
        };

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
                        printMirror.textContent = field.value;
                    });
            }
        };

        const autosize = (textarea) => {
            textarea.style.height = 'auto';
            textarea.style.height = `${Math.max(textarea.scrollHeight, 136)}px`;
            syncPrintText(textarea);
        };

        restoreReportContentStyle();
        restoreReportDraft();

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

        window.addEventListener('beforeprint', () => {
            applyReportContentStyle();
            document.querySelectorAll('.report-edit-textarea').forEach(autosize);
            reportDraftFields().forEach(syncPrintText);
        });

        window.addEventListener('load', () => {
            applyReportContentStyle();
            document.querySelectorAll('.report-edit-textarea').forEach(autosize);
        });

        const aiButton = document.getElementById('generateAiDraftsButton');
        const aiProvider = document.getElementById('aiProvider');
        const aiStatus = document.getElementById('reportAiStatus');
        const aiConfirmModal = document.getElementById('confirmAiDraftModal');
        const aiConfirmProviderLabel = document.getElementById('confirmAiProviderLabel');

        reportForm?.addEventListener('submit', () => {
            reportFormSubmitting = true;
            clearReportDraft();
        });

        window.addEventListener('beforeunload', () => {
            if (! reportFormSubmitting) {
                saveReportDraft();
            }
        });

        const showAiStatus = (message, type = 'info') => {
            if (! aiStatus) {
                return;
            }

            aiStatus.className = `alert alert-${type} report-ai-status show`;
            aiStatus.textContent = message;
        };

        const selectedAiLabel = () => aiProvider?.selectedOptions?.[0]?.textContent?.trim() || 'the selected AI candidate';

        aiConfirmModal?.addEventListener('show.bs.modal', () => {
            if (aiConfirmProviderLabel) {
                aiConfirmProviderLabel.textContent = selectedAiLabel();
            }
        });

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
            const classAssessmentKeys = [...reportForm.querySelectorAll('input[name="class_assessment_keys[]"]')]
                .map((input) => input.value.trim())
                .filter(Boolean);

            if (classAssessmentKeys.length === 0) {
                showAiStatus('No completed assessments were selected.', 'warning');
                return;
            }

            aiButton.disabled = true;
            const selectedAiName = selectedAiLabel();
            window.bootstrap?.Modal?.getInstance(aiConfirmModal)?.hide();
            showAiStatus(`Generating AI draft content with ${selectedAiName}...`, 'info');

            try {
                const response = await fetch(aiButton.dataset.aiUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.csrfToken,
                    },
                    body: JSON.stringify({
                        report_type: @json($reportType),
                        class_assessment_keys: classAssessmentKeys,
                        ai_provider: aiProvider?.value || 'openai',
                    }),
                });

                const data = await response.json();

                if (! response.ok) {
                    throw new Error(data.message ?? 'AI draft request failed.');
                }

                fillAiDrafts(data.drafts ?? {});
                saveReportDraft();

                showAiStatus(`AI draft generated with ${selectedAiName}. Review and edit before saving.`, 'success');
            } catch (error) {
                showAiStatus(error.message || 'Unable to generate AI draft. Please try again.', 'danger');
            } finally {
                aiButton.disabled = false;
            }
        });
    })();
</script>
