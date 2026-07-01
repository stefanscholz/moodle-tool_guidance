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
    ['unsetentry', 'unsetrootnode'],
    ['rootbadge', 'isrootnode'],
    ['chooserbadge', 'ischooserentry'],
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
    const MINSCALE = 0.2;
    const MAXSCALE = 2.5;
    let scale = 1;
    const nodes = new Map();
    let mode = null;
    let drag = null;
    let tempedge = null;
    let popup = null;
    // Edges must be redrawn whenever a card or answer box changes size (e.g. a
    // textarea grows or the author drags a resize handle), not just on moves.
    const resizeobserver = new ResizeObserver(() => redraw());

    /**
     * Convert a client point to surface (content) coordinates, accounting for
     * the current pan and zoom.
     *
     * @param {Number} clientx
     * @param {Number} clienty
     * @returns {Object} {x, y}
     */
    const toSurface = (clientx, clienty) => {
        const r = surface.getBoundingClientRect();
        return {x: (clientx - r.left) / scale, y: (clienty - r.top) / scale};
    };

    /**
     * Centre of a port element in surface (content) coordinates.
     *
     * @param {Element} port
     * @returns {Object} {x, y}
     */
    const portCentre = (port) => {
        const sr = surface.getBoundingClientRect();
        const pr = port.getBoundingClientRect();
        return {
            x: (pr.left + pr.width / 2 - sr.left) / scale,
            y: (pr.top + pr.height / 2 - sr.top) / scale,
        };
    };

    /**
     * Apply the current pan offset and zoom to the surface.
     */
    const applyPan = () => {
        surface.style.transform = 'translate(' + pan.x + 'px,' + pan.y + 'px) scale(' + scale + ')';
    };

    /** Reflect the current zoom level on the reset button, if present. */
    const updateZoomLabel = () => {
        const label = root.querySelector('[data-action="zoom-reset"]');
        if (label) {
            label.textContent = Math.round(scale * 100) + '%';
        }
    };

    /**
     * Zoom to a new scale while keeping the content point under (clientx, clienty)
     * fixed on screen.
     *
     * @param {Number} clientx
     * @param {Number} clienty
     * @param {Number} newscale
     */
    const zoomAt = (clientx, clienty, newscale) => {
        newscale = Math.min(MAXSCALE, Math.max(MINSCALE, newscale));
        const er = root.getBoundingClientRect();
        const px = (clientx - er.left - pan.x) / scale;
        const py = (clienty - er.top - pan.y) / scale;
        pan.x = clientx - er.left - px * newscale;
        pan.y = clienty - er.top - py * newscale;
        scale = newscale;
        applyPan();
        redraw();
        updateZoomLabel();
    };

    /** Zoom in/out around the centre of the editor viewport. */
    const zoomCentre = (factor) => {
        const er = root.getBoundingClientRect();
        zoomAt(er.left + er.width / 2, er.top + er.height / 2, scale * factor);
    };

    /** Reset to 100% zoom at the origin. */
    const resetView = () => {
        scale = 1;
        pan.x = 0;
        pan.y = 0;
        applyPan();
        redraw();
        updateZoomLabel();
    };

    /** Frame all nodes and answer boxes within the viewport. */
    const fitView = () => {
        let minx = Infinity;
        let miny = Infinity;
        let maxx = -Infinity;
        let maxy = -Infinity;
        let any = false;
        const consider = (el, x, y) => {
            minx = Math.min(minx, x);
            miny = Math.min(miny, y);
            maxx = Math.max(maxx, x + el.offsetWidth);
            maxy = Math.max(maxy, y + el.offsetHeight);
            any = true;
        };
        nodes.forEach((nd) => consider(nd.el, nd.posx, nd.posy));
        nodes.forEach((nd) => nd.links.forEach((l) => consider(l.el, l.posx, l.posy)));
        if (!any) {
            return;
        }
        const er = root.getBoundingClientRect();
        const pad = 40;
        const bw = (maxx - minx) + pad * 2;
        const bh = (maxy - miny) + pad * 2;
        scale = Math.min(MAXSCALE, Math.max(MINSCALE, Math.min(er.width / bw, er.height / bh)));
        pan.x = (er.width - (maxx - minx) * scale) / 2 - minx * scale;
        pan.y = (er.height - (maxy - miny) * scale) / 2 - miny * scale;
        applyPan();
        redraw();
        updateZoomLabel();
    };

    /**
     * Arrange every node and answer box into a tidy top-to-bottom layered tree.
     *
     * Nodes are placed in layers by their breadth-first depth from the roots
     * (deeper = further down); within a layer they are ordered by the average
     * position of their parents to keep edges from crossing. Each answer box is
     * dropped midway between its parent and child (or fanned out below the parent
     * when it dangles). The graph is acyclic (the server forbids cycles), so the
     * breadth-first walk always terminates.
     *
     * @param {Boolean} [persist] When true, save the new positions to the server.
     */
    const autoLayout = (persist) => {
        if (!nodes.size) {
            return;
        }
        // Breathing room around the answer-box row that sits in each layer gap,
        // and between sibling columns. Generous on purpose: a roomy graph the
        // author pans around beats a cramped one where boxes touch.
        const VPAD = 110;
        const HPAD = 220;

        // Count incoming edges so we can fall back to "no parents" as the roots.
        const incoming = new Map();
        nodes.forEach((nd) => incoming.set(nd.id, 0));
        nodes.forEach((nd) => nd.links.forEach((l) => {
            if (l.childnodeid && incoming.has(l.childnodeid)) {
                incoming.set(l.childnodeid, incoming.get(l.childnodeid) + 1);
            }
        }));
        let roots = [];
        nodes.forEach((nd) => {
            if (nd.isroot) {
                roots.push(nd);
            }
        });
        if (!roots.length) {
            nodes.forEach((nd) => {
                if (incoming.get(nd.id) === 0) {
                    roots.push(nd);
                }
            });
        }
        if (!roots.length) {
            roots = [nodes.values().next().value];
        }

        // Breadth-first depth from the roots.
        const level = new Map();
        const queue = [];
        roots.forEach((r) => {
            if (!level.has(r.id)) {
                level.set(r.id, 0);
                queue.push(r);
            }
        });
        while (queue.length) {
            const nd = queue.shift();
            const d = level.get(nd.id);
            nd.links.forEach((l) => {
                const c = l.childnodeid ? nodes.get(l.childnodeid) : null;
                if (c && !level.has(c.id)) {
                    level.set(c.id, d + 1);
                    queue.push(c);
                }
            });
        }
        // Anything unreachable from a root lands one layer below the deepest.
        let maxlevel = 0;
        level.forEach((d) => {
            maxlevel = Math.max(maxlevel, d);
        });
        nodes.forEach((nd) => {
            if (!level.has(nd.id)) {
                level.set(nd.id, maxlevel + 1);
            }
        });

        // Bucket nodes per layer.
        const byLevel = new Map();
        nodes.forEach((nd) => {
            const d = level.get(nd.id);
            if (!byLevel.has(d)) {
                byLevel.set(d, []);
            }
            byLevel.get(d).push(nd);
        });
        const layers = [...byLevel.keys()].sort((a, b) => a - b);
        let widest = 0;
        byLevel.forEach((arr) => {
            widest = Math.max(widest, arr.length);
        });

        // Column pitch follows the widest card so tall/short cards never touch
        // horizontally; answer boxes (narrower) always fit inside the gap.
        let maxnodew = 0;
        nodes.forEach((nd) => {
            maxnodew = Math.max(maxnodew, nd.el.offsetWidth);
        });
        const colpitch = maxnodew + HPAD;

        // Vertical pitch is measured per layer: the gap below a layer must clear
        // its tallest card, then the tallest answer box entering the next layer,
        // plus padding on each side. Fixed gaps ignored that and caused overlap.
        const layernodeh = new Map();
        layers.forEach((d) => {
            let h = 0;
            byLevel.get(d).forEach((nd) => {
                h = Math.max(h, nd.el.offsetHeight);
            });
            layernodeh.set(d, h || 120);
        });
        const answerrowh = new Map();
        layers.forEach((d) => answerrowh.set(d, 0));
        nodes.forEach((nd) => nd.links.forEach((l) => {
            const c = l.childnodeid ? nodes.get(l.childnodeid) : null;
            if (c && level.has(c.id)) {
                const d = level.get(c.id);
                answerrowh.set(d, Math.max(answerrowh.get(d) || 0, l.el.offsetHeight || 60));
            }
        }));
        const layery = new Map();
        let cursory = 60;
        layers.forEach((d, li) => {
            if (li > 0) {
                cursory += layernodeh.get(layers[li - 1]) + VPAD + (answerrowh.get(d) || 60) + VPAD;
            }
            layery.set(d, cursory);
        });

        // Order each layer by the mean order of its parents, then place it.
        const order = new Map();
        layers.forEach((d, li) => {
            const arr = byLevel.get(d);
            if (li > 0) {
                const bary = new Map();
                arr.forEach((nd) => {
                    let sum = 0;
                    let cnt = 0;
                    nodes.forEach((p) => p.links.forEach((l) => {
                        if (l.childnodeid === nd.id && order.has(p.id)) {
                            sum += order.get(p.id);
                            cnt++;
                        }
                    }));
                    bary.set(nd.id, cnt ? sum / cnt : Number.MAX_SAFE_INTEGER);
                });
                arr.sort((a, b) => bary.get(a.id) - bary.get(b.id));
            }
            const offset = (widest - arr.length) / 2 * colpitch;
            arr.forEach((nd, i) => {
                order.set(nd.id, i);
                // Centre each card within its column so uneven widths stay aligned.
                nd.posx = 60 + offset + i * colpitch + (maxnodew - nd.el.offsetWidth) / 2;
                nd.posy = layery.get(d);
                nd.el.style.left = nd.posx + 'px';
                nd.el.style.top = nd.posy + 'px';
            });
        });

        // Drop each answer box between its parent and child (or below, dangling).
        nodes.forEach((nd) => {
            const pcx = nd.posx + nd.el.offsetWidth / 2;
            const pbottom = nd.posy + nd.el.offsetHeight;
            nd.links.forEach((l, i) => {
                const aw = l.el.offsetWidth || 190;
                const ah = l.el.offsetHeight || 60;
                const child = l.childnodeid ? nodes.get(l.childnodeid) : null;
                if (child) {
                    const ccx = child.posx + child.el.offsetWidth / 2;
                    l.posx = (pcx + ccx) / 2 - aw / 2;
                    l.posy = (pbottom + child.posy) / 2 - ah / 2;
                } else {
                    l.posx = pcx - aw / 2 + (i - (nd.links.length - 1) / 2) * (aw + 30);
                    l.posy = pbottom + VPAD;
                }
                l.el.style.left = l.posx + 'px';
                l.el.style.top = l.posy + 'px';
            });
        });

        if (persist) {
            nodes.forEach((nd) => {
                if (nd.id) {
                    call('move_node', {id: nd.id, posx: nd.posx, posy: nd.posy}).catch(Notification.exception);
                }
                nd.links.forEach((l) => {
                    if (l.linkid) {
                        call('move_answer', {id: l.linkid, posx: l.posx, posy: l.posy}).catch(Notification.exception);
                    }
                });
            });
        }

        redraw();
        fitView();
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
            link.posy = nd.posy + 130 + nd.links.length * 95;
        }

        const box = document.createElement('div');
        box.className = 'tg-answer-node';
        box.style.left = link.posx + 'px';
        box.style.top = link.posy + 'px';

        const input = document.createElement('span');
        input.className = 'tg-answer-input';

        // A growing textarea (not a single-line input) so long answers stay
        // readable; the resize observer keeps the edge lines in sync.
        const label = document.createElement('textarea');
        label.className = 'form-control form-control-sm tg-answer-label';
        label.rows = 1;
        label.placeholder = s.answerlabel;
        label.value = link.label;
        const autogrow = () => {
            label.style.height = 'auto';
            label.style.height = label.scrollHeight + 'px';
        };
        label.addEventListener('mousedown', (e) => e.stopPropagation());
        label.addEventListener('input', () => {
            autogrow();
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
            resizeobserver.unobserve(box);
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
        autogrow();
        resizeobserver.observe(box);
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
        const rootbadge = document.createElement('span');
        rootbadge.className = 'tg-node-root-badge';
        rootbadge.textContent = s.rootbadge;
        const chooserbadge = document.createElement('span');
        chooserbadge.className = 'tg-node-chooser-badge';
        chooserbadge.textContent = s.chooserbadge;
        // A star toggles this node's root flag; a graph may have several roots.
        const star = document.createElement('button');
        star.type = 'button';
        star.className = 'btn btn-sm btn-link tg-node-setentry';
        star.textContent = '★';
        star.title = nd.isroot ? s.unsetentry : s.setentry;
        star.addEventListener('click', () => {
            const next = !nd.isroot;
            call('set_root', {graphid: graphid, nodeid: nd.id, isroot: next}).then(() => {
                nd.isroot = next;
                nd.el.classList.toggle('tg-node-root', next);
                star.title = next ? s.unsetentry : s.setentry;
                if (!next) {
                    // A node that is no longer a root cannot be the chooser entry.
                    nd.el.classList.remove('tg-node-entry');
                }
                return null;
            }).catch(Notification.exception);
        });
        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'btn btn-sm btn-link text-danger tg-node-delete';
        del.textContent = '×';
        del.title = s.deletenode;
        del.addEventListener('click', () => deleteNode(nd));
        header.append(type, star, rootbadge, chooserbadge, del);
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
        resizeobserver.observe(card);
        if (nd.isroot) {
            card.classList.add('tg-node-root');
        }
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
            resizeobserver.unobserve(nd.el);
            nd.el.remove();
            nodes.delete(nd.id);
            // This node's own answers go with it.
            nd.links.forEach((l) => {
                resizeobserver.unobserve(l.el);
                l.el.remove();
            });
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
        if (e.target.closest('.tg-create-popup') || e.target.closest('.tool-guidance-toolbar')) {
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
            // Mouse deltas are in screen pixels; convert to content pixels.
            drag.nd.posx = drag.origx + (e.clientX - drag.startx) / scale;
            drag.nd.posy = drag.origy + (e.clientY - drag.starty) / scale;
            drag.nd.el.style.left = drag.nd.posx + 'px';
            drag.nd.el.style.top = drag.nd.posy + 'px';
            redraw();
        } else if (mode === 'answer') {
            drag.link.posx = drag.origx + (e.clientX - drag.startx) / scale;
            drag.link.posy = drag.origy + (e.clientY - drag.starty) / scale;
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
        // The server marks the first node of a graph as a root automatically;
        // mirror that here so the badge shows without a reload.
        const nd = {
            id: 0, type: type, title: '', description: '',
            targettype: targettype, targetconfig: config,
            isroot: nodes.size === 0,
            posx: pos ? pos.x : (60 - pan.x) / scale + Math.random() * 80,
            posy: pos ? pos.y : (60 - pan.y) / scale + Math.random() * 80,
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
                isroot: !!n.isroot,
                posx: n.posx, posy: n.posy, links: []
            };
            nodes.set(nd.id, nd);
            buildCard(nd);
            // The one root the "Help me choose" chooser starts from, if it lives
            // in this graph, is highlighted as the entry.
            if (n.id === data.chooserentrynodeid) {
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
        // Bundled starter templates (e.g. the activity chooser) carry only rough
        // seed positions and no answer-box placement, so tidy them on view.
        // Positions are not persisted here: the layout is deterministic, so an
        // author who rearranges the graph by hand keeps their own arrangement.
        if (data.autolayout) {
            autoLayout(false);
        }
    };

    /**
     * Wire a toolbar action button by its data-action, if present.
     *
     * @param {String} action
     * @param {Function} handler
     */
    const onAction = (action, handler) => {
        const btn = document.querySelector('[data-action="' + action + '"]');
        if (btn) {
            btn.addEventListener('click', handler);
        }
    };

    onAction('add-question', () => addNode('question'));
    onAction('add-leaf', () => addNode('leaf'));
    onAction('zoom-in', () => zoomCentre(1.2));
    onAction('zoom-out', () => zoomCentre(1 / 1.2));
    onAction('zoom-reset', () => resetView());
    onAction('zoom-fit', () => fitView());
    onAction('auto-layout', () => autoLayout(true));
    root.addEventListener('mousedown', startPan);
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);

    // Wheel zooms toward the cursor. A gentle step keeps trackpads (which fire
    // many wheel events per gesture) from lurching between zoom levels.
    const WHEELSTEP = 1.04;
    root.addEventListener('wheel', (e) => {
        e.preventDefault();
        zoomAt(e.clientX, e.clientY, scale * (e.deltaY < 0 ? WHEELSTEP : 1 / WHEELSTEP));
    }, {passive: false});

    // Double-clicking empty canvas offers to create a question or recommendation
    // right there.
    root.addEventListener('dblclick', (e) => {
        if (e.target.closest('.tg-node') || e.target.closest('.tg-answer-node')
            || e.target.closest('.tg-create-popup')) {
            return;
        }
        showCreatePopup(e.clientX, e.clientY, () => {});
    });

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
