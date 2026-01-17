<?php
/**
 * @author WPSD Project Development Team, et al.
 *  
 */ 
header("Content-type: text/css");
$backgroundPage = "edf0f5";
$backgroundContent = "f1f1f1";
$backgroundBanners = "212529";
$textBanners = "ffffff";
$bannerDropShaddows = "121212";
$tableHeadDropShaddow = "121212";
$textContent = "000000";
$tableRowEvenBg = "f7f7f7";
$tableRowOddBg = "e0e0e0";
?>

.hidden {
display: none !important;
}

.container {
width: 100%;
text-align: left;
margin: auto;
background : #212529;
}

body, font {
font: 17px 'PT Sans', sans-serif;
color: #ffffff;
-webkit-text-size-adjust: none;
-moz-text-size-adjust: none;
-ms-text-size-adjust: none;
text-size-adjust: none;
}

.center {
text-align: center !important;
}

.middle {
vertical-align: middle;
}

.header {
background : #2e363f;
text-decoration : none;
color : #bebebe;
font-family : 'PT Sans', sans-serif;
text-align : left;
padding : 5px 0 0 0;
/* margin: 0 10px; */
}

.header h1 {
margin-top:-10px;
font-size: 34px;
}

.headerClock {
font-size: 0.9em;
text-align: left;
padding-left: 8px;
padding-top: 5px;
float: left;
}

.nav {
float: left;
margin : -12px 0 0 0;
padding : 0 3px 3px 10px;
width : 230px;
background : #212529;
font-weight : normal;
min-height : 100%;
}

.content {
margin : 0 0 0 250px;
padding : 0 10px 5px 3px;
color : #bebebe;
background : #212529;
text-align: center;
}

.contentwide {
/* padding: 10px; */
color: #bebebe;
background: #212529;
text-align: center;
margin: 5px 0 10px;
}

.contentwide h2 {
color: #bebebe;
font: 1em 'PT Sans', sans-serif;
text-align: center;
font-weight: bold;
padding: 0px;
margin: 0px;
}

.divTableCellSans h2 {
color: #000000;
}

.divTableCellMono {
font: 1.3em 'Roboto Mono', monospace !important;
}

td.divTableCellMono a:hover {
text-decoration: underline !important;
}

h2.ConfSec {
font-size: 1.6em;
text-align: left;
padding-bottom: 1rem;
}

.left {
text-align: left;
}

.footer {
background : #2e363f;
text-decoration : none;
color : #bebebe;
font-family : 'PT Sans', sans-serif;
font-size : .9rem;
text-align : center;
padding : 10px 0 10px 0;
clear : both;
margin: 10px;
}

.footer a {
text-decoration: underline !important;
color : #bebebe !important;
}

tt, code, kbd, pre {
font-family: 'Roboto Mono', monospace !important;
}

.mono {
font: 18px 'Roboto Mono', monospace !important;
}

.SmallHeader {
font-family: 'Roboto Mono', monospace !important;
font-size: 12px;
}
.shRight {
text-align: right;
padding-right: 8px;
}
.shLeft {
text-align: left;
padding-left: 8px;
float: left;
}

#tail {
font-family: 'Roboto Mono', monospace;
height: 640px;
overflow-y: scroll;
overflow-x: scroll;
color: #4DEEEA;
background: #000000;
font-size: 18px;
padding: 1em;
scrollbar-width: none; /* Firefox */
-ms-overflow-style: none; /* IE and Edge */
}

/* For Webkit browsers like Chrome/Safari */
#tail::-webkit-scrollbar {
display: none;
}

table {
vertical-align: middle;
text-align: center;
empty-cells: show;
padding: 0px;
border-collapse:collapse;
border-spacing: 5px;
border: .5px solid #3c3f47;
text-decoration: none;
background: #000000;
font-family: 'PT Sans', sans-serif;
width: 100%;
white-space: nowrap;
}

table th {
font-family: 'PT Sans', sans-serif;
border: .5px solid #3c3f47;
font-weight: 600;
text-decoration: none;
color : #bebebe;
background: #2e363f;
padding: 5px;
}

table tr:nth-child(even) {
background: #949494;
}

table tr:nth-child(odd) {
background: #7a7c80;
}

table td {
color: #000000;
text-decoration: none;
border: .5px solid #3c3f47;
padding: 5px;
font-size: 18px;
}

#ccsConns table td, #activeLinks table td, #starNetGrps table td, #infotable td, table.poc-lh-table td {
color: #000000;
font-family: 'Roboto Mono', monospace;
font-weight: 500;
text-decoration: none;
border: .5px solid #3c3f47;
padding: 5px;
font-size: 18px;
}

#liveCallerDeets table tr:hover td, #localTxs table tr:hover td, #lastHeard table tr:hover td, #bmLinks table tr:hover td,
#liveCallerDeets table tr:hover td a, #localTxs table tr:hover td a, #lastHeard table tr:hover td a, #bmLinks table tr:hover td a {
background-color: #3c3f47;
color: #ffffff;
}

.divTable{
font-family: 'PT Sans', sans-serif;
display: table;
border-collapse: collapse;
width: 100%;
}

.divTableRow {
display: table-row;
width: auto;
clear: both;
}

.divTableHead, .divTableHeadCell {
color : #bebebe;
background: #2e363f;
border: .5px solid #3c3f47;
font-weight: 600;
text-decoration: none;
padding: 5px;
caption-side: top;
display: table-caption;
text-align: center;
vertical-align: middle;
}

.divTableCellSans {
font-size: 17px;
color: #000000;
}

.divTableCell {
font-size: 17px;
border: .5px solid #3c3f47;
color: #000000;
}

.divTableCell, .divTableHeadCell {
display: table-cell;
}

.divTableBody {
display: table-row-group;
}

.divTableBody .divTableRow {
background: #949494;
}

.divTableCell.cell_content {
padding: 5px;
}

body {
background: #212529;
color: #000000;
}

a {
text-decoration:none;

}

a:link, a:visited {
text-decoration: none;
color: #1a2573}

a.tooltip, a.tooltip:link, a.tooltip:visited, a.tooltip:active {
text-decoration: none;
position: relative;
color: #bebebe;
}

a.tooltip:hover {
text-decoration: none;
background: transparent;
color: #bebebe;
}

a.tooltip span {
text-decoration: none;
display: none;
font-size: 17px;
font-family: 'PT Sans', sans-serif;
}

a.tooltip:hover span {
font-size: 17px;
font-family: 'PT Sans', sans-serif;
text-decoration: none;
display: block;
position: absolute;
top: 20px;
left: 0;
z-index: 100;
text-align: left;
white-space: nowrap;
border: none;
color: #e9e9e9;
background: rgba(0, 0, 0, .9);
padding: 8px;
}

th:last-child a.tooltip:hover span {
left: auto;
right: 0;
}

a.tooltip span b {
text-decoration: none;
display: block;
margin: 0;
font-weight: bold;
border: none;
color: #e9e9e9;
padding: 0px;
}

a.tooltip2, a.tooltip2:link, a.tooltip2:visited, a.tooltip2:active {
text-decoration: none;
position: relative;
font-weight: bold;
color: #000000;
}

a.tooltip2:hover {
text-decoration: none;
background: transparent;
color: #000000;
}

a.tooltip2 span {
text-decoration: none;
display: none;
}

a.tooltip2:hover span {
text-decoration: none;
display: block;
position: absolute;
top: 20px;
left: 0;
width: 202px;
z-index: 100;
font: 16px 'PT Sans', sans-serif;
text-align: left;
white-space: normal;
border: none;
color: #e9e9e9;
background: rgba(0, 0, 0, .9);
padding: 8px;
}

a.tooltip2 span b {
text-decoration: none;
font: 16px 'PT Sans', sans-serif;
display: block;
margin: 0;
font-weight: bold;
border: none;
color: #e9e9e9;
padding: 0px;
}

ul {
padding: 5px;
margin: 10px 0;
list-style: none;
float: left;
}

ul li {
float: left;
display: inline; /*For ignore double margin in IE6*/
margin: 0 10px;
}

ul li a {
text-decoration: none;
float:left;
color: #999;
cursor: pointer;
font: 600 14px/22px 'PT Sans', sans-serif;
}

ul li a span {
margin: 0 10px 0 -10px;
padding: 1px 8px 5px 18px;
position: relative; /*To fix IE6 problem (not displaying)*/
float:left;
}

ul.mmenu li a.current, ul.mmenu li a:hover {
color: #0d5f83;
}

ul.mmenu li a.current span, ul.mmenu li a:hover span {
color: #0d5f83;
}

h1 {
text-align: center;
font-weight: 600;
}

/* CSS Toggle Code here */
.toggle {
position: absolute;
margin-left: -9999px;
z-index: 0;
}

.toggle + label {
display: block;
position: relative;
cursor: pointer;
outline: none;
}

input.toggle-round-flat + label {
padding: 1px;
margin: 3px;
width: 33px;
height: 20px;
background-color: #5C5C5C;
border-radius: 5px;
transition: background 0.4s;
}

input.toggle-round-flat + label:before,
input.toggle-round-flat + label:after {
display: block;
position: absolute;
content: "";
}

input.toggle-round-flat + label:before {
top: 1px;
left: 1px;
bottom: 1px;
right: 1px;
background: #212529;
border-radius: 5px;
transition: background 0.4s;
}

input.toggle-round-flat + label:after {
top: 2px;
left: 2px;
bottom: 2px;
width: 16px;
background: #999;
border-radius: 5px;
transition: margin 0.4s, background 0.4s;
}

input.toggle-round-flat:checked + label {
background: #5C5C5C;
}

input.toggle-round-flat:checked + label:after {
margin-left: 14px;
background: #2c7f2c;;
}

input.toggle-round-flat:focus + label {
box-shadow: 0 0 1px #2c7f2c;;
padding: 1px;
z-index: 5;
}

.mode_flex .row {
display: flex;
flex-direction: row;
flex-wrap: wrap;
width: 100%;
}

.mode_flex .column {
display: flex;
flex-direction: column;
flex-basis: 100%;
flex: 1;
}

.mode_flex button {
background: #2e363f;
color: #bebebe;
flex-basis: 25%;
flex-shrink: 0;
text-align: center;
justify-content: center;
flex-grow: 1;
font-family: 'PT Sans', sans-serif;
border: 2px solid #3c3f47;
padding: 3px;
}

.mode_flex button > span {
align-items: center;
flex-wrap: wrap;
display: flex;
justify-content: center;
margin: 5px;
text-align: center;
}

textarea, input[type='text'], input[type='password'] {
font-size: 17px;
font-family: 'Roboto Mono', monospace;
border: 1px solid #3c3f47;
padding: 5px;
margin 3px;
background: #e2e2e2;
}

textarea.fulledit {
display: inline-block;
margin: 0;
padding: .2em;
width: auto;
min-width: 70%;
max-width: 100%;
height: auto;
min-height: 600px;
cursor: text;
overflow: auto;
resize: both;
}

input[type=button], input[type=submit], input[type=reset], input[type=radio], button {
font-size: 17px;
font-family: 'PT Sans', sans-serif;
border: 1px solid #3c3f47;
padding: 5px;
text-decoration: none;
margin: 3px;
cursor: pointer;
background: #2e363f;
color: #bebebe;
}

input[type=button]:hover, input[type=submit]:hover, input[type=reset]:hover, button:hover {
color: #ffffff;
background-color: #65737e;
}

input:disabled {
opacity: 0.7;
cursor: not-allowed;
}

button:disabled {
cursor: not-allowed;
color: #b3b3af;
background: #535353;
}

input:disabled + label {
color: #000;
opacity: 0.6;
cursor: not-allowed;
}

select {
background: #e2e2e2;
font-family: 'Roboto Mono', monospace;
font-size: 17px;
border: 1px solid #3c3f47;
color: black;
padding: 5px;
text-decoration: none;
}

.select2-selection__rendered {
font-family: 'Roboto Mono', monospace;
color: black !important;
font-size: 17px !important;
background: #e2e2e2;
}

.select2-results__options{
color: black;
font-size:17px !important;
font-family: 'Roboto Mono', monospace;
background: #e2e2e2;
}

[class^='select2'] {
border-radius: 0px !important;
}

.select2-results__option {
color: black !important;
}

.navbar {
overflow: hidden;
background-color: #2e363f;
padding: 10px 10px 10px 2px;
}

.navbar a {
float: right;
font-family : 'PT Sans', sans-serif;
font-size: 17px;
color: #bebebe;
text-align: center;
padding: 5px 8px;
text-decoration: none;
}

.dropdown .dropbutton {
font-size: 17px;
border: none;
outline: none;
color: #bebebe;
padding: 5px 8px;
background-color: #2e363f;
font-family: inherit;
margin: 0;
}

.navbar a:hover, .dropdown:hover .dropbutton {
color: #ffffff;
background-color: #65737e;
}

.lnavbar {
overflow: hidden;
background-color: #2e363f;
padding-bottom: 10px;
margin-top: -0.6rem;
}

/* Advanced menus */
.mainnav {
display: inline-block;
list-style: none;
padding: 0;
margin: 0 auto;
width: 100%;
background: #2e363f;
overflow: hidden;
}

.dropdown {
position: absolute;
top: 134px;
width: 270px;
opacity: 0;
visibility: hidden;
}

.mainnav ul {
padding: 0;
list-style: none;
}

.mainnav li {
display: block;
float: left;
font-size: 0;
margin: 0;
background: #2e363f;
}

.mainnav li a {
list-style: none;
padding: 0;
display: inline-block;
padding: 1px 10px;
font-family : 'PT Sans', sans-serif;
font-size: 17px;
color: #bebebe;
text-align: center;
text-decoration: none;
}

.mainnav .has-subs a:after {
content: "\f0d7";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-left: 1em;
}

.mainnav .has-subs .dropdown .subs a:after {
content: "";
}

.mainnav li:hover {
background: #65737e;
}

.mainnav li:hover a {
color: #ffffff;
background-color: #65737e;
}

/* First Level */
.subs {
position: relative;
width: 270px;
}

.has-subs:hover .dropdown,
.has-subs .has-subs:hover .dropdown {
opacity: 1;
visibility: visible;
}

.mainnav ul li,
.mainav ul li ul li a {
color: #ffffff;
background-color: #6b6c73;
}

.mainnav li:hover ul a,
.mainnav li:hover ul li ul li a {
color: #ffffff;
background-color: #6b6c73;
}

.mainnav li ul li:hover,
.mainnav li ul li ul li:hover {
background-color: #3c3f47;
}

.mainnav li ul li:hover a,
.mainnav li ul li ul li:hover a {
color: #ffffff;
background-color: #3c3f47;
}

.mainnav .has-subs .dropdown .has-subs a:after {
content: "\f0da";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
position: absolute;
top: 1px;
right: 9px;
}

/* Second Level */
.has-subs .has-subs .dropdown .subs {
position: relative;
top: -144px;
width: 270px;
border-style: none none none solid;
border-width: 1px;
border-color: #3c3f47;
}

.has-subs .has-subs .dropdown .subs a:after {
content:"";
}

.has-subs .has-subs .dropdown {
position: absolute;
width: 270px;
left: 270px;
opacity: 0;
visibility: hidden;
}

.menuhwinfo, .menuprofile, .menuconfig, .menuadmin, .menudashboard, .menusimple,
.menucaller, .menulive, .menuupdate, .menupower, .menulogs,
.menubackup, .menuadvanced, .menureset, .menusysinfo, .menuradioinfo,
.menuappearance {
position: relative;
}

.menuprofile:before {
content: "\f0c0";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menuappearance:before {
content: "\f1fc";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menucastmemory:before {
content: "\f0cb";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menuradioinfo:before {
content: "\f012";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menuconfig:before {
content: "\f1de";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menuadmin:before {
content: "\f023";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menuupdate:before {
content: "\f0ed";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menupower:before {
content: "\f011";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menulogs:before {
content: "\f06e";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menudashboard:before {
content: "\f0e4";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menusimple:before {
content: "\f0ce";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menulive:before {
content: "\f2a0";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menucaller:before {
content: "\f098";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.grid-item.filter-activity:before {
content: "\f131";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

tr.good-activity.even {
background: #949494;
}
tr.good-activity.odd {
background: #7a7c80;
}

input.filter-activity-max {
background-color: #949494;
color: #000000;
border: 2px solid #212529;
border-radius: 5px;
height: 19px;
}

.filter-activity-max-wrap {
display: inline-block;
position: relative;
top: -3px;
}

.menutgnames:before {
content: "\f0e6";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menuhwinfo:before {
content: "\f03a";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menubackup:before {
content: "\f187";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menuadvanced:before {
content: "\f013";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menureset:before {
content: "\f1cd";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.menusysinfo:before {
content: "\f080";
font-family: FontAwesome;
font-style: normal;
font-weight: normal;
text-decoration: inherit;
padding-right: 0.2em;
}

.disabled-service-cell {
color: #b3b3af;
background: #535353;
}

.active-service-cell {
color: #ffffff;
background: #2c7f2c;
}

.inactive-service-cell {
color: #bebebe;
background: #8C0C26;
}

.disabled-mode-cell {
color: #b3b3af;
padding:2px;
text-align: center;
border:0;
background: #535353;
}

.active-mode-cell {
color: #ffffff;
border:0;
text-align: center;
padding:2px;
background: #2c7f2c;
}

.inactive-mode-cell {
color: #bebebe;
border:0;
text-align: center;
padding:2px;
background: #8C0C26;
}

.paused-mode-cell {
color: #ffffff;
border:0;
text-align: center;
padding:2px;
background: #a65d14;
}

.paused-mode-span {
background: #a65d14;
}

.error-state-cell {
color: #bebebe;
text-align: center;
border:0;
background: #8C0C26;
}

.table-container {
position: relative;
}

.config_head {
font-size: 1.5em;
font-weight: normal;
text-align: left;
}

/* Tame Firefox Buttons */
@-moz-document url-prefix() {
select,
input {
margin : 0;
padding : 0;
border-width : 1px;
font : 14px 'Roboto Mono', monospace;
}
input[type="button"], button, input[type="submit"] {
padding : 0px 3px 0px 3px;
border-radius : 3px 3px 3px 3px;
-moz-border-radius : 3px 3px 3px 3px;
}
}

hr {
display: block;
height: 1px;
border: 0;
border-top: 1px solid #3c3f47;
margin: 1em 0;
padding: 0;
}

.status-grid {
display: grid;
grid-template-columns: auto auto auto auto auto auto;
grid-template-rows: auto auto auto auto auto;
margin:0;
padding:0;
}


.status-grid .grid-item {
padding: 1px;
border: .5px solid #3c3f47;
text-align: center;
}

@-webkit-keyframes Pulse {
from {
opacity: 0;
}

50% {
opacity: 1;
}

to {
opacity: 0;
}
}

@keyframes Pulse {
from {
opacity: 0;
}

50% {
opacity: 1;
}

to {
opacity: 0;
}
}

td.lookatme {
display: table-cell;
}

a.lookatme {
color: steelblue;
opacity: 1;
position: relative;
display: inline-block;
font-weight:bold;
font-size:10px;
padding:1px;
margin: 0 0 0 1px;
}

/* this pseudo element will be faded in and out in front /*
/* of the lookatme element to create an efficient animation. */
.lookatme:after {
color: white;
text-shadow: 0 0 5px #e33100;
/* in the html, the lookatme-text attribute must */
/* contain the same text as the .lookatme element */
content: attr(lookatme-text);
padding: inherit;
position: absolute;
inset: 0 0 0 0;
z-index: 1;
/* 20 steps / 2 seconds = 10fps */
-webkit-animation: 2s infinite Pulse steps(20);
animation: 2s infinite Pulse steps(20);
}

#hwInfoTable {
margin-top: -2px;
}

/* indicators */

.red_dot {
height: 15px;
width: 15px;
background-color: red;
border-radius: 50%;
display: inline-block;
}

.green_dot {
height: 15px;
width: 15px;
background-color: limegreen;
border-radius: 50%;
display: inline-block;
}

/* RSSI meters */
meter {
--background: #999;
--optimum: limegreen;
--sub-optimum: orange;
--sub-sub-optimum: crimson;
border-radius: 3px;
}

/* The gray background in Chrome, etc. */
meter::-webkit-meter-bar {
background: var(--background);
border-radius: 3px;
height: 10px;
}

/* The green (optimum) bar in Firefox */
meter:-moz-meter-optimum::-moz-meter-bar {
background: var(--optimum);
}

/* The green (optimum) bar in Chrome etc. */
meter::-webkit-meter-optimum-value {
background: var(--optimum);
}

/* The yellow (sub-optimum) bar in Firefox */
meter:-moz-meter-sub-optimum::-moz-meter-bar {
background: var(--sub-optimum);
}

/* The yellow (sub-optimum) bar in Chrome etc. */
meter::-webkit-meter-suboptimum-value {
background: var(--sub-optimum);
}

/* The red (even less good) bar in Firefox */
meter:-moz-meter-sub-sub-optimum::-moz-meter-bar {
background: var(--sub-sub-optimum);
}

/* The red (even less good) bar in Chrome etc. */
meter::-webkit-meter-even-less-good-value {
background: var(--sub-sub-optimum);
}

.aprs-preview-container {
display: flex;
align-items: center;
text-align: center;
margin-top: 10px;
margin-bottom: 10px;
}

.aprs-preview-text {
margin: 0 10px 0 5px;
}

.aprs-symbol-preview {
/* add'l/ any futureg styles for the symbol preview? */
}

/* Spinner animation for config pagei */
@keyframes spin {
0% { transform: rotate(0deg); }
100% { transform: rotate(360deg); }
}

.spinner {
border: 4px solid rgba(255, 255, 255, 0.3);
border-top: 4px solid #666666;
border-radius: 50%;
width: 20px;
height: 20px;
animation: spin 1s linear infinite;
display: inline-block;
margin-left: 8px;
}

/* Config page unsaved changes alert stuff */
#unsavedChanges {
display: none;
position: fixed;
top: 20px; /* Add top margin */
left: 50%;
transform: translateX(-50%); /* Center the div horizontally */
width: calc(100% - 40px);
height: 80px;
overflow: hidden;
background-color: #000;
color: #fff;
padding: 34px 10px 0px 10px;
text-align: center;
z-index: 1000;
font-size: 1.4rem;
border: 1px solid #fff;
border-radius: 10px;
max-width: 95%;
}

#applyButton {
background-color: #37803A;
border: 2px solid #73A675;
margin-left: 10px;
color: #fff;
padding: 8px 16px;
border-radius: 4px;
cursor: pointer;
transition: background-color 0.3s;
transition: color 0.3s;
font-weight: bold;
}

#applyButton:hover {
background-color: #4caf50;
border: 2px solid #37803A;
color: black;
}

#revertButton {
background-color: #e65100;
border: 2px solid #ffab40;
margin-left: 10px;
color: #fff;
padding: 8px 16px;
border-radius: 4px;
cursor: pointer;
transition: background-color 0.3s;
transition: color 0.3s;
font-weight: bold;
}

#revertButton:hover {
background-color: #ff9800;
border: 2px solid #e65100;
color: black;
}

.smaller {
font-size: smaller;
}

.larger {
font-size: larger;
}

table td.sans {
font-family: 'PT Sans', sans-serif !important;
}

div.network, div.wifiinfo, div.intinfo, div.infoheader {
border: none !important;
}