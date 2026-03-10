/**
 * WordPress PostText Export - 管理画面スクリプト
 */
(function ($) {
    'use strict';

    $(document).ready(function () {

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
