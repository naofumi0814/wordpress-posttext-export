/**
 * WordPress PostText Export - 管理画面スクリプト
 */
(function ($) {
    'use strict';

    $(document).ready(function () {

        // 件数取得ボタン
        $('#wpte-count-btn').on('click', function () {
            var $btn = $(this);
            var $result = $('#wpte-count-result');

            // ボタン無効化
            $btn.prop('disabled', true);
            $result.text(wpteAdmin.strings.counting).removeClass('has-results no-results');

            // チェックされた値を収集
            var postTypes = [];
            $('input[name="post_types[]"]:checked').each(function () {
                postTypes.push($(this).val());
            });

            var postStatuses = [];
            $('input[name="post_statuses[]"]:checked').each(function () {
                postStatuses.push($(this).val());
            });

            var categoryIds = [];
            $('input[name="category_ids[]"]:checked').each(function () {
                categoryIds.push($(this).val());
            });

            var tagIds = [];
            $('input[name="tag_ids[]"]:checked').each(function () {
                tagIds.push($(this).val());
            });

            // AJAXリクエスト（エクスポート用nonceは含めない）
            $.ajax({
                url: wpteAdmin.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wpte_count_posts',
                    nonce: wpteAdmin.nonce,
                    post_types: postTypes,
                    post_statuses: postStatuses,
                    category_ids: categoryIds,
                    tag_ids: tagIds,
                    date_from: $('input[name="date_from"]').val(),
                    date_to: $('input[name="date_to"]').val(),
                    keyword: $('input[name="keyword"]').val(),
                    post_ids: $('input[name="post_ids"]').val()
                },
                success: function (response) {
                    if (response.success) {
                        var count = response.data.count;
                        if (count > 0) {
                            $result
                                .text(wpteAdmin.strings.result.replace('%d', count))
                                .addClass('has-results')
                                .removeClass('no-results');
                        } else {
                            $result
                                .text(wpteAdmin.strings.noResults)
                                .addClass('no-results')
                                .removeClass('has-results');
                        }
                    } else {
                        $result
                            .text(wpteAdmin.strings.error)
                            .addClass('no-results')
                            .removeClass('has-results');
                    }
                },
                error: function () {
                    $result
                        .text(wpteAdmin.strings.error)
                        .addClass('no-results')
                        .removeClass('has-results');
                },
                complete: function () {
                    $btn.prop('disabled', false);
                }
            });
        });

        // カスタム区切り入力欄の表示切り替え
        function toggleCustomSeparator() {
            var selected = $('input[name="separator_type"]:checked').val();
            if (selected === 'custom') {
                $('#wpte-custom-separator').show();
            } else {
                $('#wpte-custom-separator').hide();
            }
        }

        $('input[name="separator_type"]').on('change', toggleCustomSeparator);
        toggleCustomSeparator();

        // フォーム送信確認
        $('#wpte-export-form').on('submit', function () {
            // 投稿タイプが選択されているかチェック
            if ($('input[name="post_types[]"]:checked').length === 0) {
                alert('投稿タイプを少なくとも1つ選択してください。');
                return false;
            }

            // ステータスが選択されているかチェック
            if ($('input[name="post_statuses[]"]:checked').length === 0) {
                alert('投稿ステータスを少なくとも1つ選択してください。');
                return false;
            }

            // 出力項目が選択されているかチェック
            if ($('input[name="fields[]"]:checked').length === 0) {
                alert('出力項目を少なくとも1つ選択してください。');
                return false;
            }

            return true;
        });

    });

})(jQuery);
