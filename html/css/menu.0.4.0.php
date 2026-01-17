<?php

/**
 * Исправления и дополнения к основному стилю в css.php
 * Все новые стили добавлять сюда
 * @file /css/menu.0.2.2.php
 * @version 0.2.2
 * @date 2025.12.23
 * @author vladimir@tsurkanenko.ru
 * @since 0.2.2
 *  - Добавлены стили для WebSocket статуса в стиле меню (menuwebsocket)
 *  - 3 состояния: disconnected, connected, error с соответствующими иконками FontAwesome
 *  - Убрано цветовое выделение, используется стандартный стиль меню
 * nav-> leftnav
 * @since 0.4.0
 * Убраны стили для menuwebsocket
 */
header("Content-type: text/css");
?>

.leftnav {
float: left;
margin : -12px 0 0 0;
<!-- padding : 0 3px 3px 10px; -->
padding : 0px 3px 3px 10px;
width : 230px;
background : #212529;
font-weight : normal;

}

#local_activity table tr:hover td, 
#local_activity table tr:hover td a
{
background-color: #3c3f47;
color: #ffffff;
}


.status-indicator {
display: inline-block;
width: 10px;
height: 10px;
border-radius: 50%;
margin: 0 5px;
}

.status-connected {
background: #2c7f2c;
}

.status-disconnected {
background: #8C0C26;
}

.status-connecting {
background: #a65d14;
}


/* Стили для кнопок аудио и деталей */

#conDetailTable {
margin-top: -2px;
}

.menuaudio, .menumacros, .menusettings, .menureflector, menuconnection {
position: relative;
}


.menumacros:before {
content: "\f0e7";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menusettings:before {
content: "\f085";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menuaudio:before {
content: "\f028";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menuaudio {
transition: color 0.3s ease;
}

/* Состояние mute - динамик без звука */
.menuaudio.menuaudio_mute:before {
content: "\f026";
/* динамик без звука (FA) */
}

/* Состояние active - динамик со звуком (уже в базовом классе) */
.menuaudio.menuaudio_active:before {
content: "\f028";
/* динамик со звуком (FA) */
}

.menuconnection:before {
content: "\f1c0";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.icon-active:before {
color: #2c7f2c;;
}

.grid-item.audio-monitor:before {
content: "\f028";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

#radio_activity a.tooltip,
#radio_activity a.tooltip:link,
#radio_activity a.tooltip:visited,
#radio_activity a.tooltip:active
{
color: #000000;
}

.paused-mode-cell a.tooltip,
.paused-mode-cell a.tooltip:link,
.paused-mode-cell a.tooltip:visited,
.paused-mode-cell a.tooltip:active,
.active-mode-cell a.tooltip,
.active-mode-cell a.tooltip:link,
.active-mode-cell a.tooltip:visited,
.active-mode-cell a.tooltip:active,
.inactive-mode-cell a.tooltip,
.inactive-mode-cell a.tooltip:link,
.inactive-mode-cell a.tooltip:visited,
.inactive-mode-cell a.tooltip:active {
color: #ffffff;
}

span.error {
/* font-weight: bold; */
color: #ffffff;
background-color: #8C0C26;
padding: 0.2em 0.4em;
-webkit-border-radius: 0.2em;
-moz-border-radius: 0.2em;
border-radius: 0.2em;
}

span.success {
/* font-weight: bold; */
color: #ffffff;
background-color:rgba(23, 140, 12, 0.84);
padding: 0.2em 0.4em;
-webkit-border-radius: 0.2em;
-moz-border-radius: 0.2em;
border-radius: 0.2em;
}

span.operate {
/* font-weight: bold; */
color: #ffffff;
background-color: #2c7f2c;
padding: 0.2em 0.4em;
-webkit-border-radius: 0.2em;
-moz-border-radius: 0.2em;
border-radius: 0.2em;
}

.auth-overlay {
display: none;
position: fixed;
top: 0;
left: 0;
width: 100%;
height: 100%;
background: rgba(0, 0, 0, 0.7);
z-index: 9998;
backdrop-filter: blur(3px);
}

.auth-container {
position: fixed;
top: 50%;
left: 50%;
transform: translate(-50%, -50%);
z-index: 9999;
width: 400px;
max-width: 90%;
}

.auth-form {
background: #212529;
border: 2px solid #3c3f47;
padding: 25px;
box-shadow: 0 0 25px rgba(0, 0, 0, 0.9);
border-radius: 12px;
}

.auth-title {
color: #bebebe;
text-align: center;
margin-bottom: 25px;
font-size: 1.5em;
font-weight: 600;
}

.auth-field {
margin-bottom: 20px;
}

.auth-field label {
display: block;
color: #bebebe;
margin-bottom: 8px;
font-size: 16px;
}

.auth-field input[type="text"],
.auth-field input[type="password"] {
width: 100%;
padding: 12px 15px;
font-size: 16px;
border: 1px solid #3c3f47;
background: #2e363f;
color: #ffffff;
border-radius: 8px;
box-sizing: border-box;
transition: all 0.3s ease;
}

.auth-field input[type="text"]:focus,
.auth-field input[type="password"]:focus {
outline: none;
border-color: #65737e;
box-shadow: 0 0 5px rgba(101, 115, 126, 0.5);
}

.auth-field input[type="text"]::placeholder,
.auth-field input[type="password"]::placeholder {
color: #949494;
}

.auth-buttons {
display: flex;
justify-content: space-between;
margin-top: 25px;
gap: 10px;
}

.auth-buttons button {
flex: 1;
padding: 12px 20px;
font-size: 16px;
font-weight: 600;
border: none;
border-radius: 8px;
cursor: pointer;
transition: all 0.3s ease;
}

.auth-buttons button[type="submit"] {
background: #2c7f2c;
color: #ffffff;
}

.auth-buttons button[type="submit"]:hover {
background: #37803A;
transform: translateY(-1px);
}

.auth-buttons button[type="button"] {
background: #5C5C5C;
color: #bebebe;
}

.auth-buttons button[type="button"]:hover {
background: #65737e;
color: #ffffff;
transform: translateY(-1px);
}

.auth-error {
color: #ff6b6b;
text-align: center;
margin-top: 15px;
font-size: 14px;
padding: 10px;
background: rgba(140, 12, 38, 0.2);
border-radius: 6px;
display: none;
}

.auth-close {
position: absolute;
top: 15px;
right: 15px;
background: none;
border: none;
color: #bebebe;
font-size: 24px;
cursor: pointer;
width: 30px;
height: 30px;
border-radius: 50%;
display: flex;
align-items: center;
justify-content: center;
transition: all 0.3s ease;
}

.auth-close:hover {
color: #ffffff;
background: rgba(255, 255, 255, 0.1);
}

/* Анимация появления */
.auth-container {
animation: authSlideIn 0.6s ease-out;
}

@keyframes authSlideIn {
from {
opacity: 0;
transform: translate(-50%, -60%);
}

to {
opacity: 1;
transform: translate(-50%, -50%);
}
}

/* Адаптивность для мобильных устройств */
@media (max-width: 480px) {
.auth-container {
width: 95%;
}

.auth-form {
padding: 20px;
}

.auth-buttons {
flex-direction: column;
}

.auth-buttons button {
margin-bottom: 10px;
}
}

/* Стили для поля ввода последовательности DTMF */
.keypad-sequence-container {
margin-bottom: 15px;
}

.keypad-sequence-field {
display: flex;
gap: 10px;
margin-bottom: 5px;
}

.keypad-sequence-input {
flex: 1;
padding: 12px 15px;
font-size: 18px;
font-family: 'Roboto Mono', monospace;
border: 2px solid #3c3f47;
background: #535353;
color: #b3b3af;
border-radius: 8px;
transition: all 0.3s ease;
}

.keypad-sequence-input:focus {
outline: none;
border-color: #65737e;
box-shadow: 0 0 5px rgba(101, 115, 126, 0.5);
background: #5C5C5C;
color: #ffffff;
}

.keypad-sequence-input:disabled {
opacity: 0.5;
cursor: not-allowed;
}

.keypad-sequence-send {
padding: 12px 20px;
font-size: 16px;
font-weight: 600;
border: 2px solid #3c3f47;
background: #2c7f2c;
color: #ffffff;
border-radius: 8px;
cursor: pointer;
transition: all 0.3s ease;
white-space: nowrap;
display: flex;
align-items: center;
justify-content: center;
min-width: 140px;
}

.keypad-sequence-send:hover:not(:disabled) {
background: #37803A;
transform: translateY(-1px);
}

.keypad-sequence-send:disabled {
opacity: 0.5;
cursor: not-allowed;
background: #5C5C5C;
}

.keypad-sequence-hint {
text-align: center;
color: #949494;
font-size: 12px;
margin-top: 5px;
}

/* Центральное всплывающее окно */
.keypad-toast-center {
position: fixed;
top: 50%;
left: 50%;
transform: translate(-50%, -50%);
z-index: 10001;
padding: 20px 30px;
border-radius: 12px;
font-weight: bold;
box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
min-width: 300px;
max-width: 80%;
text-align: center;
color: #ffffff;
font-size: 16px;
display: flex;
align-items: center;
justify-content: center;
animation: toastFadeIn 0.3s ease-out;
}

@keyframes toastFadeIn {
from {
opacity: 0;
transform: translate(-50%, -40%);
}

to {
opacity: 1;
transform: translate(-50%, -50%);
}
}

/* @deprecated Стили для новой компоновки DTMF keypad */
.keypad-main-grid {
display: grid;
grid-template-columns: 3fr 1fr;
gap: 15px;
margin-bottom: 15px;
}

.keypad-numpad {
display: grid;
grid-template-columns: repeat(3, 1fr);
gap: 10px;
}

.keypad-letters {
display: grid;
grid-template-rows: repeat(4, 1fr);
gap: 10px;
}

.keypad-letter {
min-height: 50px;
}

.keypad-bottom-row {
margin-top: 10px;
width: 100%;
}

.keypad-disconnect {
width: 100%;
grid-column: 1 / -1;
}

/* Анимация появления для keypad */
.keypad-container {
animation: keypadSlideIn 0.4s ease-out;
}

@keyframes keypadSlideIn {
from {
opacity: 0;
transform: translate(-50%, -60%);
}

to {
opacity: 1;
transform: translate(-50%, -50%);
}
}

/* Адаптивность для мобильных устройств для keypad */
@media (max-width: 480px) {
.keypad-container {
width: 95%;
}

.keypad-form {
padding: 15px;
}

.keypad-button {
padding: 12px 5px;
font-size: 18px;
min-height: 50px;
}

.keypad-sequence-field {
flex-direction: column;
}

.keypad-sequence-send {
width: 100%;
min-width: auto;
}

.keypad-main-grid {
grid-template-columns: 1fr;
gap: 10px;
}

.keypad-letters {
grid-template-columns: repeat(4, 1fr);
grid-template-rows: auto;
}

.keypad-letter {
min-height: 45px;
}

.keypad-toast-center {
min-width: 250px;
padding: 15px 20px;
font-size: 14px;
}
}

/* Toast уведомления для keypad */
#keypadToast {
display: none;
}

.menukeypad,
.menureflector {
position: relative;
}

/* Иконка для меню DTMF */
.menukeypad:before {
content: "\f095";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menureflector:before {
content: "\f0a1";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menureconnection:before {
content: "\f1e6";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.link-control:before {
content: "\f127";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

/* @deprecated Стили для заблокированного тумблера */
.toggle:disabled+label {
opacity: 0.5;
cursor: not-allowed;
}

.toggle:disabled+label::before {
background-color: #666 !important;
border-color: #444 !important;
}

.toggle:disabled+label::after {
background-color: #999 !important;
}

/* Стиль для контейнера с заблокированным тумблером */
.grid-item div[style*="position: relative"]:has(.toggle:disabled) {
opacity: 0.7;
}

/* Текст "Unlink" для заблокированного состояния */
.filter-activity[style*="color: #999"] {
font-style: italic;
}

/* @deprecated === НОВЫЕ СТИЛИ ДЛЯ УПРАВЛЕНИЯ ЛИНКАМИ === */

/* Стили для тумблера управления линками */
.link-toggle {
cursor: pointer;
}

.link-toggle:disabled {
cursor: not-allowed;
opacity: 0.6;
}

/* Контейнер для заблокированного тумблера */
.grid-item div[style*="position: relative"]:has(.link-toggle:disabled) {
opacity: 0.7;
}

.grid-item div[style*="position: relative"]:has(.link-toggle:disabled)::after {
position: absolute;
top: 50%;
left: 50%;
transform: translate(-50%, -50%);
font-size: 12px;
color: #fff;
z-index: 2;
pointer-events: none;
}

/* Анимация для тумблера во время отправки */
.link-toggle.sending {
animation: linkPulse 1s infinite;
}

@keyframes linkPulse {
0% {
opacity: 1;
}

50% {
opacity: 0.5;
}

100% {
opacity: 1;
}
}

/* @deprecated Стили для уведомлений линков */
#linkToastContainer {
position: fixed;
top: 20px;
right: 20px;
z-index: 10001;
display: flex;
flex-direction: column;
gap: 10px;
max-width: 400px;
}

.link-toast {
padding: 12px 20px;
border-radius: 6px;
font-weight: bold;
box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
color: white;
opacity: 0;
transform: translateX(100%);
transition: opacity 0.3s ease, transform 0.3s ease;
min-width: 250px;
max-width: 350px;
word-wrap: break-word;
font-size: 14px;
}

.link-toast.success {
background: #2c7f2c;
border-left: 4px solid #1a5a1a;
}

.link-toast.error {
background: #8C0C26;
border-left: 4px solid #5c0819;
}

.link-toast.info {
background: #2e363f;
border-left: 4px solid #1a2026;
}

/* @deprecated Адаптивность для уведомлений линков */
@media (max-width: 768px) {
#linkToastContainer {
top: 10px;
right: 10px;
left: 10px;
max-width: none;
}

.link-toast {
min-width: auto;
max-width: none;
width: 100%;
}
}

/* Стили для состояния загрузки */
.link-loading {
position: relative;
}

.link-loading::after {
content: "";
position: absolute;
top: 0;
left: 0;
right: 0;
bottom: 0;
background: rgba(0, 0, 0, 0.5);
border-radius: inherit;
z-index: 1;
}

.link-loading .toggle {
position: relative;
z-index: 2;
}

/* Стили для текста "Unlink" в разных состояниях */
.filter-activity.disabled {
color: #999 !important;
font-style: italic;
}

.filter-activity.loading {
color: #ffa500 !important;
animation: textPulse 1s infinite;
}

@keyframes textPulse {
0% {
opacity: 1;
}

50% {
opacity: 0.7;
}

100% {
opacity: 1;
}
}

/* Стили для кнопок DTMF keypad */
.dtmf-btn {
padding: 8px 3px;
font-size: 16px;
font-weight: 600;
border: 1px solid #3c3f47;
border-radius: 6px;
cursor: pointer;
transition: all 0.15s ease;
display: flex;
align-items: center;
justify-content: center;
min-height: 35px;
outline: none;
}

.dtmf-num {
background: #2e363f;
color: #bebebe;
}

.dtmf-num:hover:not(:disabled) {
background: #65737e;
color: #ffffff;
transform: translateY(-1px);
box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.dtmf-special,
.dtmf-letter {
background: #a65d14;
color: #ffffff;
}

.dtmf-special:hover:not(:disabled),
.dtmf-letter:hover:not(:disabled) {
background: #b86b20;
transform: translateY(-1px);
box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.dtmf-info {
background: #2c7f2c;
color: #ffffff;
}

.dtmf-info:hover:not(:disabled) {
background: #37803A;
transform: translateY(-1px);
box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.dtmf-disconnect {
background: #8C0C26;
color: #ffffff;
width: 100%;
font-size: 14px;
padding: 8px;
}

.dtmf-disconnect:hover:not(:disabled) {
background: #a00e2d;
transform: translateY(-1px);
box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.dtmf-btn:disabled {
opacity: 0.5;
cursor: not-allowed;
background: #5C5C5C;
}

.dtmf-btn:active:not(:disabled) {
transform: translateY(0);
}

/* Адаптивность для DTMF keypad */
@media (max-width: 480px) {
#keypadContainer {
width: 95% !important;
transform: translate(-50%, -50%) scale(0.95) !important;
}

.dtmf-btn {
padding: 6px 2px;
font-size: 14px;
min-height: 30px;
}

#keypadToast {
min-width: 200px;
padding: 12px 18px;
font-size: 13px;
}
}

/* === НОВЫЕ СТИЛИ ДЛЯ УПРАВЛЕНИЯ БЛОКОМ ДАННЫХ СОЕДИНЕНИЙ === */

/* @deprecated Стили для блока данных соединений */
#conInfo {
overflow: hidden;
background: #212529;
border-radius: 4px;
box-sizing: border-box;
}

/* @deprecated Классы для анимации раскрытия/скрытия */
.coninfo-hidden {
max-height: 0 !important;
opacity: 0 !important;
margin: 0 !important;
padding: 0 !important;
border: none !important;
overflow: hidden !important;
}

.coninfo-visible {
opacity: 1 !important;
margin-bottom: 10px !important;
padding: 5px !important;
border: 1px solid #3c3f47 !important;
overflow: visible !important;
}

/* @deprecated Плавная анимация раскрытия/скрытия */
#conInfo {
transition:
max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1),
opacity 0.3s ease 0.1s,
margin 0.3s ease,
padding 0.3s ease,
border 0.3s ease;
}

/* @deprecated Индикатор состояния в кнопке меню Connect Details */
.menuradioinfo.active {
color: #2c7f2c !important;
background-color: rgba(44, 127, 44, 0.1) !important;
}

.menuradioinfo.with-indicator:after {
content: " \f0d7"; /* Стрелка вниз (FontAwesome) */
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
margin-left: 5px;
transition: transform 0.3s ease;
display: inline-block;
}

.menuradioinfo.active.with-indicator:after {
content: " \f0d8"; /* Стрелка вверх (FontAwesome) */
transform: rotate(180deg);
}

/* @deprecated Для предотвращения мерцания при загрузке */
.coninfo-init {
visibility: hidden;
opacity: 0;
}

/* @deprecated Адаптивность для блока соединений */
@media (max-width: 768px) {
#conInfo {
margin-left: 5px;
margin-right: 5px;
}

.coninfo-visible {
padding: 3px !important;
margin-bottom: 8px !important;
}
}

/* @deprecated Анимация для контента внутри блока при обновлении */
#conInfo .divTable {
animation: fadeInContent 0.3s ease;
}

@keyframes fadeInContent {
from {
opacity: 0.7;
transform: translateY(-5px);
}
to {
opacity: 1;
transform: translateY(0);
}
}

/* @deprecated Состояние загрузки для блока */
#conInfo.loading {
position: relative;
min-height: 50px;
}

#conInfo.loading:after {
content: "";
position: absolute;
top: 0;
left: 0;
right: 0;
bottom: 0;
background: rgba(33, 37, 41, 0.8);
display: flex;
align-items: center;
justify-content: center;
color: #bebebe;
font-size: 14px;
z-index: 10;
}

/* Состояние ошибки для блока */
#conInfo.error-state {
border-color: #8C0C26 !important;
background-color: rgba(140, 12, 38, 0.05) !important;
}

/* Минимальная высота для предотвращения скачков */
#conInfo {
min-height: 0;
}

.active-radio-cell {
color: #ffffff;
border:0;
text-align: center;
margin: - 5px;
background: #2c7f2c;
}

.inactive-mode-cell {
color: #bebebe;
border:0;
text-align: center;
padding:-5px;
background: #8C0C26;
}

.debug-console {
font-family: 'Monaco', 'Consolas', monospace;
font-size: 12px;
background: #1e1e1e;
color: #d4d4d4;
border: 1px solid #333;
border-radius: 4px;
margin: 10px;
max-height: 400px;
overflow-y: auto;
}

.debug-entry {
padding: 2px 5px;
border-bottom: 1px solid #2d2d2d;
}

.debug-entry:hover {
background: #2a2a2a;
}

.debug-time {
color: #6a9955;
margin-right: 10px;
}

.debug-source {
color: #569cd6;
margin-right: 10px;
}

.debug-error { color: #f44747; }
.debug-warning { color: #ff8800; }
.debug-info { color: #4ec9b0; }
.debug-debug { color: #888; }