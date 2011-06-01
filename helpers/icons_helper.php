<?php

function ico_delete($alt) {
    return '<img src="'.get_instance()->config->slash_item('theme_folder_url').'third_party/prolib/images/delete.png" alt="'.$alt.'"/>';
}
function ico_layout($alt) {
    return '<img src="'.get_instance()->config->slash_item('theme_folder_url').'third_party/prolib/images/layout.png" alt="'.$alt.'"/>';
}
function ico_defaults($alt) {
    return '<img src="'.get_instance()->config->slash_item('theme_folder_url').'third_party/prolib/images/defaults.png" alt="'.$alt.'"/>';
}
function ico_entries($alt) {
    return '<img src="'.get_instance()->config->slash_item('theme_folder_url').'third_party/prolib/images/entries.png" alt="'.$alt.'"/>';
}
function ico_move($alt) {
    return '<img src="'.get_instance()->config->slash_item('theme_folder_url').'third_party/prolib/images/move.png" alt="'.$alt.'"/>';
}
