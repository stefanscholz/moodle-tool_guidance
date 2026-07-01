// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Hand-rolled guidance graph editor: HTML cards over an SVG edge layer.
 *
 * No third-party library. The graph flows top to bottom. Questions and leaves
 * are node cards; each answer is its own small in-between box that sits between
 * a question and the node it leads to. An answer may dangle (no child yet),
 * which is how an unfinished branch is authored.
 *
 * Dragging from a question's output port drops an answer box: onto a node it is
 * created already pointing there, onto empty space it is created dangling.
 * Dragging from an answer box's output port points it at a child node, or onto
 * empty space opens a menu to create and link a new node. All mutations go
 * through the tool_guidance web services, which enforce the no-cycle and
 * parent-is-question rules server-side.
 *
 * @module     guidanceaddon_editor/editor
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getStrings} from 'core/str';

const SVGNS = 'http://www.w3.org/2000/svg';

/** @type {Array} String keys to load, mapped onto the s.* names used in the UI. */
const STRINGKEYS = [
    ['typequestion', 'nodetype:question'],
    ['typeleaf', 'nodetype:leaf'],
    ['title', 'nodetitle'],
    ['description', 'nodedescription'],
    ['targettype', 'nodetargettype'],
    ['answerlabel', 'answerlabel'],
    ['removeanswer', 'deleteanswer'],
    ['deletenode', 'deletenode'],
    ['setentry', 'setrootnode'],
    ['entrybadge', 'isrootnode'],
    ['untitled', 'untitlednode'],
    ['confirmdeletenode', 'confirmdeletenodejs'],
    ['createhere', 'createnodehere'],
    ['modname', 'target:activity:modname'],
    ['path', 'target:route:path'],
    ['url', 'target:url:url'],
    ['newwindow', 'target:url:newwindow'],
];

/**
 * Call a single tool_guidance web service method.
 *
 * @param {String} method Short name without the guidanceaddon_editor_ prefix.
 * @param {Object} args
 * @returns {Promise}
 */
const call = (method, args) => Ajax.call([{methodname: 'guidanceaddon_editor_' + method, args: args}])[0];

/**
 * Initialise the editor on the current page.
 *
 * @param {Number} graphid
 */
export const init = (graphid) => {
    let s = {};
    let targettypes = [];
    let activitymods = [];
    const root = document.querySelector('[data-region="editor"]');
    const surface = root.querySelector('[data-region="surface"]');
    const edges = root.querySelector('[data-region="edges"]');
    const nodelayer = root.querySelector('[data-region="nodes"]');
    // Answer boxes live below the node cards so dropping a connection onto a
    // card is never intercepted by an answer box.
    const answerlayer = document.createElement('div');
    answerlayer.className = 'tg-answer-layer';
    surface.insertBefore(answerlayer, nodelayer);
    const pan = {x: 0, y: 0};
    const nodes = new Map();
    let mode = null;
    let drag = null;
    let tempedge = null;
    let popup = null;

    /**
     * Convert a client point to surface (content) coordinates.
     *
     * @param {Number} clientx
     * @param {Number} clienty
     * @returns {Object} {x, y}
     */
    const toSurface = (clientx, clienty) => {
        const r = surface.getBoundingClientRect();
        return {x: clientx - r.left, y: clienty - r.top};
    };

    /**
     * Centre of a port element in surface coordinates.
     *
     * @param {Element} port
     * @returns {Object} {x, y}
     */
    const portCentre = (port) => {
        const sr = surface.getBoundingClientRect();
        const pr = port.getBoundingClientRect();
        return {x: pr.left + pr.width / 2 - sr.left, y: pr.top + pr.height / 2 - sr.top};
    };

    /**
     * Apply the current pan offset to the surface.
     */
    const applyPan = () => {
        surface.style.transform = 'translate(' + pan.x + 'px,' + pan.y + 'px)';
    };

    /**
     * Build a vertical cubic bezier path between two points (top to bottom).
     *
     * @param {Object} a {x, y}
     * @param {Object} b {x, y}
     * @returns {String}
     */
    const bezier = (a, b) => {
        const dy = Math.max(30, Math.abs(b.y - a.y) / 2);
        return 'M' + a.x + ',' + a.y + ' C' + a.x + ',' + (a.y + dy) + ' '
            + b.x + ',' + (b.y - dy) + ' ' + b.x + ',' + b.y;
    };

    /**
     * Append one edge path to the SVG layer.
     *
     * @param {Object} from {x, y}
     * @param {Object} to {x, y}
     */
    const drawEdge = (from, to) => {
        const path = document.createElementNS(SVGNS, 'path');
        path.setAttribute('d', bezier(from, to));
        path.setAttribute('class', 'tg-edge');
        edges.appendChild(path);
    };

    /**
     * Redraw every edge: question to answer box, and answer box to its child.
     */
    const redraw = () => {
        while (edges.firstChild) {
            edges.removeChild(edges.firstChild);
        }
        nodes.forEach((nd) => {
            if (!nd.outport) {
                return;
            }
            nd.links.forEach((link) => {
                drawEdge(portCentre(nd.outport), portCentre(link.input));
                if (link.childnodeid && nodes.has(link.childnodeid)) {
                    drawEdge(portCentre(link.outport), portCentre(nodes.get(link.childnodeid).input));
                } else if (link.childnodeid) {
                    // Child vanished underneath us; fall back to dangling.
                    link.childnodeid = 0;
                }
            });
        });
    };

    /**
     * Build the JSON target config for a leaf node.
     *
     * @param {Object} nd
     * @returns {String}
     */
    const targetConfigJson = (nd) => {
        if (nd.type !== 'leaf') {
            return '';
        }
        return JSON.stringify(nd.targetconfig || {});
    };

    let savetimer = null;
    /**
     * Persist a node's content (debounced).
     *
     * @param {Object} nd
     */
    const queueSave = (nd) => {
        window.clearTimeout(savetimer);
        savetimer = window.setTimeout(() => {
            call('save_node', {
                graphid: graphid,
                id: nd.id,
                type: nd.type,
                title: nd.title || '',
                description: nd.description || '',
                targettype: nd.type === 'leaf' ? (nd.targettype || '') : '',
                targetconfig: targetConfigJson(nd),
                posx: nd.posx,
                posy: nd.posy
            }).then((res) => {
                if (res.error) {
                    Notification.addNotification({message: res.error, type: 'error'});
                }
                return res;
            }).catch(Notification.exception);
        }, 400);
    };

    /**
     * Render the target-config fields for a leaf node into its container.
     *
     * @param {Object} nd
     */
    const renderTargetConfig = (nd) => {
        const box = nd.el.querySelector('.tg-target-config');
        box.innerHTML = '';
        const cfg = nd.targetconfig || {};
        const field = (labeltext, input) => {
            const label = document.createElement('label');
            label.className = 'tg-field';
            label.append(labeltext, input);
            box.appendChild(label);
        };
        if (nd.targettype === 'activity') {
            const sel = document.createElement('select');
            sel.className = 'form-select form-select-sm';
            activitymods.forEach((m) => {
                const o = document.createElement('option');
                o.value = m.value;
                o.textContent = m.label;
                if (m.value === cfg.modname) {
                    o.selected = true;
                }
                sel.appendChild(o);
            });
            sel.addEventListener('change', () => {
                nd.targetconfig = {modname: sel.value};
                queueSave(nd);
            });
            if (!cfg.modname && activitymods.length) {
                nd.targetconfig = {modname: activitymods[0].value};
            }
            field(s.modname, sel);
        } else if (nd.targettype === 'route') {
            const inp = document.createElement('input');
            inp.type = 'text';
            inp.className = 'form-control form-control-sm';
            inp.value = cfg.path || '';
            inp.addEventListener('input', () => {
                nd.targetconfig = {path: inp.value};
                queueSave(nd);
            });
            field(s.path, inp);
        } else if (nd.targettype === 'url') {
            const inp = document.createElement('input');
            inp.type = 'text';
            inp.className = 'form-control form-control-sm';
            inp.value = cfg.url || '';
            const chk = document.createElement('input');
            chk.type = 'checkbox';
            chk.checked = !!cfg.newwindow;
            const sync = () => {
                nd.targetconfig = {url: inp.value, newwindow: chk.checked};
                queueSave(nd);
            };
            inp.addEventListener('input', sync);
            chk.addEventListener('change', sync);
            field(s.url, inp);
            const ck = document.createElement('label');
            ck.className = 'tg-field tg-check';
            ck.append(chk, s.newwindow);
            box.appendChild(ck);
        }
    };

    /**
     * Build the in-between answer box for one link and register it on its parent.
     *
     * @param {Object} nd Parent (question) node.
     * @param {Object} data {linkid, label, childnodeid, posx, posy}
     */
    const buildAnswer = (nd, data) => {
        const link = {
            linkid: data.linkid || 0,
            label: data.label || '',
            childnodeid: data.childnodeid || 0,
            posx: data.posx || 0,
            posy: data.posy || 0
        };
        // Legacy answers (and any without a stored position) stack below the
        // question so they do not all pile up at the origin.
        if (!link.posx && !link.posy) {
            link.posx = nd.posx + 40;
            link.posy = nd.posy + 130 + nd.links.length * 70;
        }

        const box = document.createElement('div');
        box.className = 'tg-answer-node';
        box.style.left = link.posx + 'px';
        box.style.top = link.posy + 'px';

        const input = document.createElement('span');
        input.className = 'tg-answer-input';

        const label = document.createElement('input');
        label.type = 'text';
        label.className = 'form-control form-control-sm tg-answer-label';
        label.placeholder = s.answerlabel;
        label.value = link.label;
        label.addEventListener('mousedown', (e) => e.stopPropagation());
        label.addEventListener('input', () => {
            link.label = label.value;
            if (link.linkid) {
                call('update_link', {id: link.linkid, answerlabel: link.label}).catch(Notification.exception);
            }
        });

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-sm btn-link text-danger tg-answer-remove';
        remove.textContent = '×';
        remove.title = s.removeanswer;
        remove.addEventListener('mousedown', (e) => e.stopPropagation());
        remove.addEventListener('click', () => {
            if (link.linkid) {
                call('delete_link', {id: link.linkid}).catch(Notification.exception);
            }
            nd.links = nd.links.filter((l) => l !== link);
            box.remove();
            redraw();
        });

        const outport = document.createElement('span');
        outport.className = 'tg-answer-outport';
        outport.title = s.answerlabel;
        outport.addEventListener('mousedown', (e) => startConnectAnswer(e, link));

        box.append(input, label, remove, outport);
        box.addEventListener('mousedown', (e) => startAnswerDrag(e, link));

        link.el = box;
        link.input = input;
        link.outport = outport;
        answerlayer.appendChild(box);
        nd.links.push(link);
    };

    /**
     * Create a node card element and wire its controls.
     *
     * @param {Object} nd
     */
    const buildCard = (nd) => {
        const card = document.createElement('div');
        card.className = 'tg-node tg-node-' + nd.type;
        card.style.left = nd.posx + 'px';
        card.style.top = nd.posy + 'px';
        nd.el = card;

        const input = document.createElement('span');
        input.className = 'tg-node-input';
        nd.input = input;

        const header = document.createElement('div');
        header.className = 'tg-node-header';
        const type = document.createElement('span');
        type.className = 'tg-node-type';
        type.textContent = nd.type === 'leaf' ? s.typeleaf : s.typequestion;
        const entry = document.createElement('span');
        entry.className = 'tg-node-entry-badge';
        entry.textContent = s.entrybadge;
        const star = document.createElement('button');
        star.type = 'button';
        star.className = 'btn btn-sm btn-link tg-node-setentry';
        star.textContent = '★';
        star.title = s.setentry;
        star.addEventListener('click', () => {
            call('set_root', {graphid: graphid, nodeid: nd.id})
                .then(() => window.location.reload()).catch(Notification.exception);
        });
        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'btn btn-sm btn-link text-danger tg-node-delete';
        del.textContent = '×';
        del.title = s.deletenode;
        del.addEventListener('click', () => deleteNode(nd));
        header.append(type, star, entry, del);
        header.addEventListener('mousedown', (e) => startNodeDrag(e, nd));

        const body = document.createElement('div');
        body.className = 'tg-node-body';

        const title = document.createElement('input');
        title.type = 'text';
        title.className = 'form-control form-control-sm tg-title';
        title.placeholder = s.title;
        title.value = nd.title;
        title.addEventListener('input', () => {
            nd.title = title.value;
            queueSave(nd);
        });

        const desc = document.createElement('textarea');
        desc.className = 'form-control form-control-sm tg-desc';
        desc.rows = 2;
        desc.placeholder = s.description;
        desc.value = nd.description;
        desc.addEventListener('input', () => {
            nd.description = desc.value;
            queueSave(nd);
        });

        body.append(title, desc);

        if (nd.type === 'leaf') {
            const tt = document.createElement('select');
            tt.className = 'form-select form-select-sm tg-targettype';
            targettypes.forEach((t) => {
                const o = document.createElement('option');
                o.value = t.value;
                o.textContent = t.label;
                if (t.value === nd.targettype) {
                    o.selected = true;
                }
                tt.appendChild(o);
            });
            tt.addEventListener('change', () => {
                nd.targettype = tt.value;
                nd.targetconfig = {};
                renderTargetConfig(nd);
                queueSave(nd);
            });
            const cfg = document.createElement('div');
            cfg.className = 'tg-target-config';
            body.append(tt, cfg);
        }

        card.append(input, header, body);
        // Only questions have outgoing answers, so only they get an output port.
        if (nd.type !== 'leaf') {
            const outport = document.createElement('span');
            outport.className = 'tg-node-outport';
            outport.title = s.answerlabel;
            outport.addEventListener('mousedown', (e) => startConnect(e, nd));
            nd.outport = outport;
            card.appendChild(outport);
        }
        nodelayer.appendChild(card);
        if (nd.type === 'leaf') {
            renderTargetConfig(nd);
        }
    };

    /**
     * Delete a node and its card after confirmation.
     *
     * @param {Object} nd
     */
    const deleteNode = (nd) => {
        // eslint-disable-next-line no-alert
        if (!window.confirm(s.confirmdeletenode)) {
            return;
        }
        call('delete_node', {id: nd.id}).then(() => {
            nd.el.remove();
            nodes.delete(nd.id);
            // This node's own answers go with it.
            nd.links.forEach((l) => l.el.remove());
            // Answers from other questions that pointed here become dangling
            // (the server already detached them).
            nodes.forEach((other) => {
                other.links.forEach((l) => {
                    if (l.childnodeid === nd.id) {
                        l.childnodeid = 0;
                    }
                });
            });
            redraw();
            return null;
        }).catch(Notification.exception);
    };

    /**
     * Begin dragging a node card.
     *
     * @param {MouseEvent} e
     * @param {Object} nd
     */
    const startNodeDrag = (e, nd) => {
        if (e.target.closest('button')) {
            return;
        }
        closePopup();
        e.preventDefault();
        mode = 'node';
        drag = {nd: nd, startx: e.clientX, starty: e.clientY, origx: nd.posx, origy: nd.posy};
    };

    /**
     * Begin dragging an answer box.
     *
     * @param {MouseEvent} e
     * @param {Object} link
     */
    const startAnswerDrag = (e, link) => {
        closePopup();
        e.preventDefault();
        mode = 'answer';
        drag = {link: link, startx: e.clientX, starty: e.clientY, origx: link.posx, origy: link.posy};
    };

    /**
     * Begin dragging a new answer from a question's output port.
     *
     * @param {MouseEvent} e
     * @param {Object} nd
     */
    const startConnect = (e, nd) => {
        e.preventDefault();
        e.stopPropagation();
        closePopup();
        mode = 'connect-q';
        drag = {nd: nd};
        tempedge = document.createElementNS(SVGNS, 'path');
        tempedge.setAttribute('class', 'tg-edge tg-edge-temp');
        edges.appendChild(tempedge);
    };

    /**
     * Begin dragging a child connection from an answer box's output port.
     *
     * @param {MouseEvent} e
     * @param {Object} link
     */
    const startConnectAnswer = (e, link) => {
        e.preventDefault();
        e.stopPropagation();
        closePopup();
        mode = 'connect-a';
        drag = {link: link};
        tempedge = document.createElementNS(SVGNS, 'path');
        tempedge.setAttribute('class', 'tg-edge tg-edge-temp');
        edges.appendChild(tempedge);
    };

    /**
     * Begin panning the surface.
     *
     * @param {MouseEvent} e
     */
    const startPan = (e) => {
        if (e.target.closest('.tg-create-popup')) {
            return;
        }
        closePopup();
        if (e.target.closest('.tg-node') || e.target.closest('.tg-answer-node')) {
            return;
        }
        mode = 'pan';
        drag = {startx: e.clientX, starty: e.clientY, origx: pan.x, origy: pan.y};
    };

    /**
     * Global pointer move dispatcher.
     *
     * @param {MouseEvent} e
     */
    const onMove = (e) => {
        if (mode === 'node') {
            drag.nd.posx = drag.origx + (e.clientX - drag.startx);
            drag.nd.posy = drag.origy + (e.clientY - drag.starty);
            drag.nd.el.style.left = drag.nd.posx + 'px';
            drag.nd.el.style.top = drag.nd.posy + 'px';
            redraw();
        } else if (mode === 'answer') {
            drag.link.posx = drag.origx + (e.clientX - drag.startx);
            drag.link.posy = drag.origy + (e.clientY - drag.starty);
            drag.link.el.style.left = drag.link.posx + 'px';
            drag.link.el.style.top = drag.link.posy + 'px';
            redraw();
        } else if (mode === 'pan') {
            pan.x = drag.origx + (e.clientX - drag.startx);
            pan.y = drag.origy + (e.clientY - drag.starty);
            applyPan();
        } else if (mode === 'connect-q') {
            tempedge.setAttribute('d', bezier(portCentre(drag.nd.outport), toSurface(e.clientX, e.clientY)));
        } else if (mode === 'connect-a') {
            tempedge.setAttribute('d', bezier(portCentre(drag.link.outport), toSurface(e.clientX, e.clientY)));
        }
    };

    /**
     * Find the node whose card is under a client point, if any.
     *
     * @param {Number} clientx
     * @param {Number} clienty
     * @returns {Object|null}
     */
    const nodeAt = (clientx, clienty) => {
        const el = document.elementFromPoint(clientx, clienty);
        const card = el && el.closest('.tg-node');
        if (!card) {
            return null;
        }
        let found = null;
        nodes.forEach((nd) => {
            if (nd.el === card) {
                found = nd;
            }
        });
        return found;
    };

    /**
     * Create an answer for a question, optionally already pointing at a child.
     *
     * @param {Object} nd Parent question.
     * @param {Number} childid Child node id, or 0 for a dangling answer.
     * @param {Number} posx
     * @param {Number} posy
     */
    const createAnswer = (nd, childid, posx, posy) => {
        call('create_link', {
            graphid: graphid,
            parentnodeid: nd.id,
            childnodeid: childid,
            answerlabel: '',
            posx: posx,
            posy: posy
        }).then((res) => {
            if (res.error) {
                Notification.addNotification({message: res.error, type: 'error'});
            } else {
                buildAnswer(nd, {linkid: res.id, label: '', childnodeid: childid, posx: posx, posy: posy});
                redraw();
            }
            return res;
        }).catch(Notification.exception);
    };

    /**
     * Point an existing answer at a child node (server enforces no-cycle/no-self).
     *
     * @param {Object} link
     * @param {Number} childid
     */
    const linkAnswer = (link, childid) => {
        call('link_answer', {id: link.linkid, childnodeid: childid}).then((res) => {
            if (res.error) {
                Notification.addNotification({message: res.error, type: 'error'});
            } else {
                link.childnodeid = childid;
                redraw();
            }
            return res;
        }).catch(Notification.exception);
    };

    /**
     * Remove the new-node menu if it is open.
     */
    const closePopup = () => {
        if (popup) {
            popup.remove();
            popup = null;
        }
    };

    /**
     * Offer to create a new node where a connection was dropped, then run a
     * callback with the created node.
     *
     * @param {Number} clientx
     * @param {Number} clienty
     * @param {Function} oncreated Receives the new node object.
     */
    const showCreatePopup = (clientx, clienty, oncreated) => {
        closePopup();
        const menu = document.createElement('div');
        menu.className = 'tg-create-popup';
        const er = root.getBoundingClientRect();
        menu.style.left = (clientx - er.left) + 'px';
        menu.style.top = (clienty - er.top) + 'px';
        const at = toSurface(clientx, clienty);
        const heading = document.createElement('div');
        heading.className = 'tg-create-popup-heading';
        heading.textContent = s.createhere;
        menu.appendChild(heading);
        const option = (labeltext, type) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-sm btn-secondary';
            b.textContent = labeltext;
            b.addEventListener('click', () => {
                closePopup();
                addNode(type, {x: at.x - 120, y: at.y}).then((nd) => {
                    if (nd) {
                        oncreated(nd);
                    }
                    return nd;
                });
            });
            menu.appendChild(b);
        };
        option(s.typequestion, 'question');
        option(s.typeleaf, 'leaf');
        root.appendChild(menu);
        popup = menu;
    };

    /**
     * Resolve a drop while dragging from a question's output port: create an
     * answer linked to the node under the cursor, or a dangling answer on empty.
     *
     * @param {MouseEvent} e
     */
    const finishQuestionConnect = (e) => {
        const at = toSurface(e.clientX, e.clientY);
        const target = nodeAt(e.clientX, e.clientY);
        if (target && target.id !== drag.nd.id) {
            const from = portCentre(drag.nd.outport);
            const to = portCentre(target.input);
            createAnswer(drag.nd, target.id, (from.x + to.x) / 2 - 75, (from.y + to.y) / 2 - 20);
        } else {
            createAnswer(drag.nd, 0, at.x - 75, at.y - 20);
        }
    };

    /**
     * Resolve a drop while dragging from an answer box: point it at the node
     * under the cursor, or open the create-node menu on empty space.
     *
     * @param {MouseEvent} e
     */
    const finishAnswerConnect = (e) => {
        const link = drag.link;
        const target = nodeAt(e.clientX, e.clientY);
        if (target) {
            linkAnswer(link, target.id);
        } else {
            showCreatePopup(e.clientX, e.clientY, (nd) => linkAnswer(link, nd.id));
        }
    };

    /**
     * Global pointer up dispatcher.
     *
     * @param {MouseEvent} e
     */
    const onUp = (e) => {
        if (tempedge) {
            tempedge.remove();
            tempedge = null;
        }
        if (mode === 'node' && drag.nd.id) {
            call('move_node', {id: drag.nd.id, posx: drag.nd.posx, posy: drag.nd.posy})
                .catch(Notification.exception);
        } else if (mode === 'answer' && drag.link.linkid) {
            call('move_answer', {id: drag.link.linkid, posx: drag.link.posx, posy: drag.link.posy})
                .catch(Notification.exception);
        } else if (mode === 'connect-q') {
            finishQuestionConnect(e);
        } else if (mode === 'connect-a') {
            finishAnswerConnect(e);
        }
        mode = null;
        drag = null;
    };

    /**
     * Create a new node of the given type and persist it.
     *
     * @param {String} type
     * @param {Object} [pos] Optional {x, y} in surface coordinates.
     * @returns {Promise} Resolves to the node object, or null on failure.
     */
    const addNode = (type, pos) => {
        if (type === 'leaf' && !targettypes.length) {
            return Promise.resolve(null);
        }
        const targettype = type === 'leaf' ? targettypes[0].value : '';
        // Seed a valid default for activity leaves so the stored config matches
        // the option the UI pre-selects; other types need author input first.
        const config = (targettype === 'activity' && activitymods.length)
            ? {modname: activitymods[0].value} : {};
        const nd = {
            id: 0, type: type, title: '', description: '',
            targettype: targettype, targetconfig: config,
            posx: pos ? pos.x : 60 - pan.x + Math.random() * 80,
            posy: pos ? pos.y : 60 - pan.y + Math.random() * 80,
            links: []
        };
        return call('save_node', {
            graphid: graphid, id: 0, type: type, title: '', description: '',
            targettype: nd.targettype, targetconfig: type === 'leaf' ? JSON.stringify(config) : '',
            posx: nd.posx, posy: nd.posy
        }).then((res) => {
            if (res.error) {
                Notification.addNotification({message: res.error, type: 'error'});
                return null;
            }
            nd.id = res.id;
            nodes.set(nd.id, nd);
            buildCard(nd);
            return nd;
        }).catch((ex) => {
            Notification.exception(ex);
            return null;
        });
    };

    /**
     * Build the editor from server data.
     *
     * @param {Object} data
     */
    const load = (data) => {
        targettypes = data.targettypes;
        activitymods = data.activitymods;
        data.nodes.forEach((n) => {
            const nd = {
                id: n.id, type: n.type, title: n.title, description: n.description,
                targettype: n.targettype || (n.type === 'leaf' ? targettypes[0].value : ''),
                targetconfig: n.targetconfig ? JSON.parse(n.targetconfig) : {},
                posx: n.posx, posy: n.posy, links: []
            };
            nodes.set(nd.id, nd);
            buildCard(nd);
            if (n.id === data.rootnodeid) {
                nd.el.classList.add('tg-node-entry');
            }
        });
        data.links.forEach((l) => {
            const parent = nodes.get(l.parentnodeid);
            if (parent) {
                buildAnswer(parent, {
                    linkid: l.id, label: l.answerlabel, childnodeid: l.childnodeid,
                    posx: l.posx, posy: l.posy
                });
            }
        });
        redraw();
    };

    document.querySelector('[data-action="add-question"]').addEventListener('click', () => addNode('question'));
    document.querySelector('[data-action="add-leaf"]').addEventListener('click', () => addNode('leaf'));
    root.addEventListener('mousedown', startPan);
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);

    applyPan();
    getStrings(STRINGKEYS.map(([, key]) => ({key: key, component: 'tool_guidance'})))
        .then((loaded) => {
            STRINGKEYS.forEach(([name], i) => {
                s[name] = loaded[i];
            });
            return call('get_graph', {graphid: graphid});
        })
        .then((data) => {
            load(data);
            return data;
        })
        .catch(Notification.exception);
};
