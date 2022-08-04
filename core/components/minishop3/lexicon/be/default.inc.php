<?php

/**
 * Default Belorussian Lexicon Entries for MiniShop3
 *
 * @package MiniShop3
 * @subpackage lexicon
 */

include_once('setting.inc.php');
$files = scandir(dirname(__FILE__));
foreach ($files as $file) {
    if (strpos($file, 'msp.') === 0) {
        @include_once($file);
    }
}

$_lang['miniShop3'] = 'MiniShop3';
$_lang['ms_menu_desc'] = 'Дзіўнае пашырэнне электроннай камерцыі';
$_lang['ms_order'] = 'Заказ';
$_lang['ms_orders'] = 'Заказы';
$_lang['ms_orders_intro'] = 'Панэль кіравання заказамі. Вы можаце выбіраць адразу некалькі заказаў праз Shift або Ctrl';
$_lang['ms_orders_desc'] = 'Упраўленне заказамі';
$_lang['ms_settings'] = 'Настройкі';
$_lang['ms_settings_intro'] = 'Панэль кіравання наладамі магазіна. Тут вы можаце паказаць спосабы аплаты, дастаўкі і статусы заказаў';
$_lang['ms_settings_desc'] = 'Статусы заказаў, параметры аплаты і дастаўкі';
$_lang['ms_payment'] = 'Аплата';
$_lang['ms_payments'] = 'Спосабы аплаты';
$_lang['ms_payments_intro'] = 'Вы можаце ствараць любыя спосабы аплаты заказаў. Логіка аплаты (адпраўка пакупніка на аддалены сэрвіс, прыём аплаты і да т.п.) рэалізуецца ў класе, які вы пакажаце.<br/>Для метадаў аплаты параметр "клас" абавязковы.';
$_lang['ms_delivery'] = 'Дастаўка';
$_lang['ms_deliveries'] = 'Варыянты дастаўкі';
$_lang['ms_deliveries_intro'] = 'Магчымыя варыянты дастаўкі. Логіка падлічвання кошту дастаўкі ў залежнасці ад адлегласці і вагі рэалізуецца класам, які вы пакажа ў наладах.<br/>Калі вы не пазначыце свой клас, разлік будзе вырабляцца алгарытмам па-змаўчанні.';
$_lang['ms_statuses'] = 'Статусы заказа';
$_lang['ms_statuses_intro'] = 'Існуе некалькі абавязковых статусаў заказа: "новы", "аплочаны", "адпраўлены" і "адменены". Іх можна наладжваць, але нельга выдаляць, так як яны неабходныя для працы магазіну. Вы можаце паказаць свае статуты для пашыранай логікі працы з заказамі.<br/>Статус можа быць канчатковым, гэта значыць, што яго нельга пераключыць на іншы, напрыклад "адпраўлены" і "адменены". Статус можа быць зафіксаваны, гэта значыць, з яго нельга пераключацца на больш раннія статусы, напрыклад "аплочаны" нельга пераключыць на "новы".';
$_lang['ms_vendors'] = 'Вытворцы тавараў';
$_lang['ms_vendors_intro'] = 'Спіс магчымых вытворцаў тавараў. Тое, што вы сюды дадасце, можна выбраць у поле "vendor" тавару.';
$_lang['ms_link'] = 'Сувязь тавараў';
$_lang['ms_links'] = 'Сувязі тавараў';
$_lang['ms_links_intro'] = 'Спіс магчымых сувязяў тавараў адзін з адным. Тып сувязі характарызуе, як менавіта яна будзе працаваць, яго нельга ствараць, можна толькі выбраць з спісу.';
$_lang['ms_option'] = 'Ўласцівасць тавараў';
$_lang['ms_options'] = 'Ўласцівасці тавараў';
$_lang['ms_options_intro'] = 'Спіс магчымых уласцівасцяў тавараў. Дрэва катэгорый выкарыстоўваецца для фільтрацыі уласцівасцяў выбраных катэгорый.<br/>Каб прызначыць катэгорыях адразу некалькі опцый, выберыце іх праз Ctrl (Cmd) або Shift.';
$_lang['ms_options_category_intro'] = 'Спіс магчымых уласцівасцяў тавараў у дадзенай катэгорыі.';
$_lang['ms_default_value'] = 'Значэнне па змаўчанні';
$_lang['ms_customer'] = 'Пакупнік';
$_lang['ms_all'] = 'Усе';
$_lang['ms_type'] = 'Тып';

$_lang['ms_btn_create'] = 'Стварыць';
$_lang['ms_btn_copy'] = 'Скапіяваць';
$_lang['ms_btn_save'] = 'Сохранить';
$_lang['ms_btn_edit'] = 'Змяніць';
$_lang['ms_btn_view'] = 'Прагляд';
$_lang['ms_btn_delete'] = 'Выдаліць';
$_lang['ms_btn_undelete'] = 'Аднавіць';
$_lang['ms_btn_publish'] = 'Уключыць';
$_lang['ms_btn_unpublish'] = 'Адключыць';
$_lang['ms_btn_cancel'] = 'Адмена';
$_lang['ms_btn_back'] = 'Назад (alt + &uarr;)';
$_lang['ms_btn_prev'] = 'Папярэдні тавар (alt + &larr;)';
$_lang['ms_btn_next'] = 'Наступны тавар (alt + &rarr;)';
$_lang['ms_btn_help'] = 'Дапамога';
$_lang['ms_btn_duplicate'] = 'Зрабіць копію тавару';
$_lang['ms_btn_addoption'] = 'Дадаць';
$_lang['ms_btn_assign'] = 'Прызначыць';

$_lang['ms_actions'] = 'Дзеянні';
$_lang['ms_search'] = 'Пошук';
$_lang['ms_search_clear'] = 'Ачысціць';

$_lang['ms_category'] = 'Катэгорыя тавараў';
$_lang['ms_category_tree'] = 'Дрэва катэгорый';
$_lang['ms_category_type'] = 'Катэгорыя тавараў';
$_lang['ms_category_create'] = 'Дадаць катэгорыю';
$_lang['ms_category_create_here'] = 'Катэгорыю з таварамі';
$_lang['ms_category_manage'] = 'Кіраванне таварамі';
$_lang['ms_category_duplicate'] = 'Капіяваць катэгорыю';
$_lang['ms_category_publish'] = 'Апублікаваць катэгорыю';
$_lang['ms_category_unpublish'] = 'Прыбраць з публікацыі';
$_lang['ms_category_delete'] = 'Выдаліць катэгорыю';
$_lang['ms_category_undelete'] = 'Аднавіць катэгорыю';
$_lang['ms_category_view'] = 'Паглядзець на сайце';
$_lang['ms_category_new'] = 'Новая катэгорыя';
$_lang['ms_category_option_add'] = 'Дадаць характарыстыку';
$_lang['ms_category_option_rank'] = 'Парадак сартавання';
$_lang['ms_category_show_nested'] = 'Паказваць укладзеныя тавары';

$_lang['ms_product'] = 'Тавар магазіна';
$_lang['ms_product_type'] = 'Тавар магазіна';
$_lang['ms_product_create_here'] = 'Тавар магазіна';
$_lang['ms_product_create'] = 'Дадаць тавар';

$_lang['ms_option_type'] = 'Тып уласцівасці';

$_lang['ms_frontend_currency'] = 'руб.';
$_lang['ms_frontend_weight_unit'] = 'кг.';
$_lang['ms_frontend_count_unit'] = 'шт.';
$_lang['ms_frontend_add_to_cart'] = 'Дадаць у кошык';
$_lang['ms_frontend_tags'] = 'Тэгі';
$_lang['ms_frontend_colors'] = 'Колеры';
$_lang['ms_frontend_color'] = 'Колер';
$_lang['ms_frontend_sizes'] = 'Памеры';
$_lang['ms_frontend_size'] = 'Памер';
$_lang['ms_frontend_popular'] = 'Папулярны тавар';
$_lang['ms_frontend_favorite'] = 'Рэкамендаваць';
$_lang['ms_frontend_new'] = 'Новы';
$_lang['ms_frontend_deliveries'] = 'Варыянты дастаўкі';
$_lang['ms_frontend_delivery'] = 'Дастаўка';
$_lang['ms_frontend_payments'] = 'Спосабы аплаты';
$_lang['ms_frontend_payment'] = 'Аплата';
$_lang['ms_frontend_delivery_select'] = 'Выберыце дастаўку';
$_lang['ms_frontend_payment_select'] = 'Выберыце аплату';
$_lang['ms_frontend_credentials'] = 'Дадзеныя атрымальніка';
$_lang['ms_frontend_address'] = 'Адрас дастаўкі';

$_lang['ms_frontend_comment'] = 'Каментарый';
$_lang['ms_frontend_receiver'] = 'Атрымальнік';
$_lang['ms_frontend_email'] = 'Email';
$_lang['ms_frontend_phone'] = 'Тэлефон';
$_lang['ms_frontend_index'] = 'Паштовы індекс';
$_lang['ms_frontend_country'] = 'Краіна';
$_lang['ms_frontend_region'] = 'Рэгіён / Вобласць';
$_lang['ms_frontend_city'] = 'Горад';
$_lang['ms_frontend_street'] = 'Вуліца';
$_lang['ms_frontend_building'] = 'Дом';
$_lang['ms_frontend_room'] = 'Кватэра';

$_lang['ms_frontend_order_cost'] = 'Разам, з дастаўкай';
$_lang['ms_frontend_order_submit'] = 'Зрабіць заказ!';
$_lang['ms_frontend_order_cancel'] = 'Ачысціць форму';
$_lang['ms_frontend_order_success'] = 'Дзякуй за афармленне заказу<b>#[[+num]]</b> на нашым сайце <b>[[++site_name]]</b>!';

$_lang['ms_message_close_all'] = 'зачыніць усё';
$_lang['ms_err_unknown'] = 'Невядомая памылка';
$_lang['ms_err_ns'] = 'Гэта поле абавязковае';
$_lang['ms_err_ae'] = 'Гэта поле павінна быць унікальна';
$_lang['ms_err_json'] = 'Гэта поле патрабуе JSON радок';
$_lang['ms_err_user_nf'] = 'Карыстальнік не знойдзены.';
$_lang['ms_err_order_nf'] = 'Заказ з такім ідэнтыфікатарам не знойдзены.';
$_lang['ms_err_status_nf'] = 'Статус з такім ідэнтыфікатарам не знойдзены.';
$_lang['ms_err_delivery_nf'] = 'Спосаб дастаўкі з такім ідэнтыфікатарам не знойдзены.';
$_lang['ms_err_payment_nf'] = 'Спосаб аплаты з такім ідэнтыфікатарам не знойдзены.';
$_lang['ms_err_status_final'] = 'Усталяваны фінальны статус. Яго нельга змяняць.';
$_lang['ms_err_status_fixed'] = 'Усталяваны фінальны статус. Вы не можаце змяніць яго на больш ранні.';
$_lang['ms_err_status_wrong'] = 'Няправільны статус заказу.';
$_lang['ms_err_status_same'] = 'Гэты статус ўжо усталяваны.';
$_lang['ms_err_register_globals'] = 'Памылка: php параметр <b>register_globals</b> павінен быць выключаны.';
$_lang['ms_err_link_equal'] = 'Вы спрабуеце дадаць тавару спасылку на самога сябе';
$_lang['ms_err_value_duplicate'] = 'Вы не ўвялі значэнне або ўвялі паўтор.';

$_lang['ms_err_gallery_save'] = 'Немагчыма захаваць файл';
$_lang['ms_err_gallery_ns'] = 'Немагчыма прачытаць файл';
$_lang['ms_err_gallery_ext'] = 'Няправільнае пашырэнне файла';
$_lang['ms_err_gallery_thumb'] = 'Не атрымалася згенераваць прэв\'юшкі. Глядзіце сістэмны лог.';
$_lang['ms_err_gallery_exists'] = 'Такі малюнак ужо ёсць у галерэі тавару.';
$_lang['ms_err_wrong_image'] = 'Файл не з\'яўляецца карэктным малюнкам.';

$_lang['ms_email_subject_new_user'] = 'Вы зрабілі заказ #[[+num]] на сайце [[++site_name]]';
$_lang['ms_email_subject_new_manager'] = 'У вас новы заказ #[[+num]]';
$_lang['ms_email_subject_paid_user'] = 'Вы аплацілі заказ #[[+num]]';
$_lang['ms_email_subject_paid_manager'] = 'Заказ #[[+num]] быў аплочаны';
$_lang['ms_email_subject_sent_user'] = 'Ваш заказ #[[+num]] быў адпраўлены';
$_lang['ms_email_subject_cancelled_user'] = 'Ваш заказ #[[+num]] быў адменены';

$_lang['ms_payment_link'] = 'Калі вы выпадкова перапынілі працэдуру аплаты, Вы заўсёды можаце <a href="[[+link]]" style="color:#348eda;">працягнуць яе па гэтай спасылцы</a>.';

$_lang['ms_category_err_ns'] = 'Катэгорыя не абраная';
$_lang['ms_option_err_ns'] = 'Ўласцівасць не абрана';
$_lang['ms_option_err_nf'] = 'Ўласцівасць не знойдзена';
$_lang['ms_option_err_ae'] = 'Ўласцівасць ўжо існуе';
$_lang['ms_option_err_save'] = 'Памылка захавання уласцівасці';
$_lang['ms_option_err_reserved_key'] = 'Такі ключ опцыі выкарыстоўваць нельга';
$_lang['ms_option_err_invalid_key'] = 'Няправільны ключ для ўласцівасці';
