<?php
// Version: 2.0; ManageCalendar

$txt['manage_calendar'] = 'Calendrier';
$txt['manage_holidays'] = 'Gérer les jours fériés';
$txt['calendar_settings'] = 'Paramètres';

$txt['calendar_desc'] = 'Ici vous pouvez modifier tous les aspects du calendrier.';

$txt['help_cal_enabled'] = 'Le calendrier peut êre utilisé afin d\'afficher les dates importantes à votre communauté.<br><br>
		<strong>Montrer les jours en tant que liens vers \'Poster un Événement\'</strong>&#8239;:<br>Permet à vos membres de poster des événements pour ce jour, lorsqu\'ils cliquent sur la date.<br>
		<strong>Jours d\'avance max. sur l\'accueil</strong>:<br>Si cette option est mise à 7, tous les événements de la semaine à venir seront montrés.<br>
		<strong>Montrer les jours de fête sur l\'accueil du forum</strong>&#8239;:<br>Montre les jours de fête dans une barre sur l\'accueil du forum.<br>
		<strong>Montrer les événements sur l\'accueil du forum</strong>&#8239;:<br>Affiche les événements du jour dans une barre sur l\'accueil du forum.<br>
		<strong>Section où Poster par Défaut</strong>:<br>Quelle est la section par défaut pour poster les événements&#8239;?<br>
		<strong>Permettre les événements qui ne sont liés à aucun message</strong>&#8239;:<br>Permet aux membres de poster des événements sans nécessiter la création d\'un nouveau sujet dans le forum.<br>
		<strong>Année minimale</strong>&#8239;:<br>Sélectionne la "première" année dans la liste du calendrier.<br>
		<strong>Année maximale</strong>&#8239;:<br>Sélectionne la "dernière" année dans la liste du calendrier<br>
		<strong>Permettre aux événements de durer plusieurs jours</strong>&#8239;:<br>Sélectionnez pour permettre aux événements de durer plusieurs jours.<br>
		<strong>Durée maximale (en jours) d\'un événement</strong>&#8239;:<br>Sélectionnez le nombre maximal de jours pour la duré d\'un événement.<br><br>
		Rappelez-vous que l\'usage du calendrier (poster des événements, voir des événements, etc.) est contrôlable par les réglages des permissions à partir de l\'écran de gestion des permissions.';

// Calendar Settings
$txt['calendar_settings_desc'] = 'Ici vous pouvez activer le calendrier et déterminer les réglages qu\'il devrait suivre.';
$txt['save_settings'] = 'Enregistrer les paramètres';
$txt['groups_calendar_view'] = 'Groupes de membres autorisés à voir le calendrier';
$txt['groups_calendar_post'] = 'Groupes de membres autorisés à créer des événements';
$txt['groups_calendar_edit_own'] = 'Groupes de membres autorisés à modifier leurs propres événements';
$txt['groups_calendar_edit_any'] = 'Groupes de membres autorisés à modifier tous les événements';
$txt['setting_cal_daysaslink'] = 'Montrer les jours en tant que liens vers \'Poster un événement\'';
$txt['setting_cal_days_for_index'] = 'Nombres de jours à l\'avance sur l\'accueil du forum';
$txt['setting_cal_showholidays'] = 'Montrer les fêtes';
$txt['setting_cal_showevents'] = 'Montrer les événements';
$txt['setting_cal_show_never'] = 'Jamais';
$txt['setting_cal_show_cal'] = 'Dans le calendrier seulement';
$txt['setting_cal_show_index'] = 'Sur l\'accueil du forum seulement';
$txt['setting_cal_show_all'] = 'Montrer sur l\'accueil du forum et dans le calendrier';
$txt['setting_cal_defaultboard'] = 'Section par défaut dans laquelle poster les événements';
$txt['setting_cal_allow_unlinked'] = 'Permettre les événements qui ne sont liés à aucun message';
$txt['setting_cal_minyear'] = 'Année minimum';
$txt['setting_cal_maxyear'] = 'Année maximum';
$txt['setting_cal_allowspan'] = 'Permettre aux événements de durer plusieurs jours';
$txt['setting_cal_maxspan'] = 'Nombre maximum de jours que peut durer un événement';
$txt['setting_cal_showInTopic'] = 'Montrer les événements liés lors de l\'affichage des sujets';

// Adding/Editing/Viewing Holidays
$txt['manage_holidays_desc'] = 'Ici vous pouvez ajouter et enlever des fêtes de votre calendrier sur votre forum.';
$txt['predefined_holidays'] = 'Fêtes prédéfinies';
$txt['custom_holidays'] = 'Fêtes personnalisées';
$txt['holidays_title'] = 'Fête';
$txt['holidays_title_label'] = 'Titre';
$txt['holidays_delete_confirm'] = 'Êtes-vous sûr de vouloir enlever ces fêtes ?';
$txt['holidays_add'] = 'Ajouter une nouvelle fête';
$txt['holidays_edit'] = 'Modifier la fête existante';
$txt['holidays_button_add'] = 'Ajouter';
$txt['holidays_button_edit'] = 'Modifier';
$txt['holidays_button_remove'] = 'Enlever';
$txt['holidays_no_entries'] = 'Aucun jour férié configuré pour le moment.';
$txt['every_year'] = 'Tous les ans';

// Maintenance
$txt['repair_operation_missing_calendar_topics'] = 'Événements liés à un sujet inexistant';
$txt['repair_missing_calendar_topics'] = 'L\'événement #%1$d est lié à un sujet inexistant, #%2$d.';

// Permissions
$txt['permissiongroup_calendar'] = 'Calendrier';
$txt['permissionname_calendar_view'] = 'Voir le calendrier';
$txt['permissionhelp_calendar_view'] = 'Le calendrier affiche pour chaque mois les événements et les jours fériés. Cette permission autorise l\'accès à ce calendrier. Quand cette permission est validée, un bouton est ajouté à la barre de menu principal et une liste est affichée au bas de l\'accueil du forum avec les événements et fêtes courants et à venir. Le calendrier doit être activé depuis la page <em>Options principales</em>.';
$txt['permissionname_calendar_post'] = 'Créer des événements dans le calendrier';
$txt['permissionhelp_calendar_post'] = 'Un événement est un sujet lié à une certaine date ou plage de dates. Vous pouvez créer des événements depuis le calendrier. Un événement ne peut être créé que par un utilisateur qui a la permission de poster des nouveaux sujets.';
$txt['permissionname_calendar_edit'] = 'Modifier les événements du calendrier';
$txt['permissionhelp_calendar_edit'] = 'Un événement est un sujet lié à une certaine date ou plage de dates. Il peut être modifié en cliquant l\'astérisque rouge (<span style="color: red;">*</span>) sur la page du calendrier. Pour modifier un événement, l\'utilisateur doit avoir les permissions suffisantes pour modifier le premier message du sujet lié à cet événement.';
$txt['permissionname_calendar_edit_own'] = 'Événements personnels';
$txt['permissionname_calendar_edit_any'] = 'Tous les événements';

// Reporting
$txt['group_perms_name_calendar_edit_any'] = 'Modifier n\'importe quel événement';
$txt['group_perms_name_calendar_edit_own'] = 'Modifier ses propres événements';
$txt['group_perms_name_calendar_post'] = 'Poster des événements';
$txt['group_perms_name_calendar_view'] = 'Voir les événements';

?>