ms3.DragZone = function (view) {
    this.view = view;
    ms3.DragZone.superclass.constructor.call(this, view.getEl());
};
Ext.extend(ms3.DragZone, Ext.dd.DragZone, {

    getDragData: function (e) {
        const target = e.getTarget(this.view.itemSelector);
        if (!target) {
            return false;
        } else if (!this.view.isSelected(target)) {
            this.view.onClick(e);
        }

        const selNodes = this.view.getSelectedNodes();
        if (selNodes.length > 1) {
            return false;
        }

        return {
            nodes: selNodes,
            ddel: target,
            single: true,
        };
    }
});


ms3.DropZone = function (view) {
    this.view = view;
    ms3.DropZone.superclass.constructor.call(this, view.getEl(), {containerScroll: true});
};
Ext.extend(ms3.DropZone, Ext.dd.DropZone, {

    getTargetFromEvent: function (e) {
        return e.getTarget(this.view.itemSelector);
    },

    onNodeEnter: function (target) {
        Ext.fly(target).addClass('x-view-selected');
    },

    onNodeOut: function (target) {
        Ext.fly(target).removeClass('x-view-selected');
    },

    onNodeOver: function (target, dd, e, data) {
        return Ext.dd.DropZone.prototype.dropAllowed && (target !== data.nodes[0]);
    },

    onNodeDrop: function (target, dd, e, data) {
        const targetNode = this.view.getRecord(target);
        const sourceNode = this.view.getRecord(data.nodes[0]);
        if (sourceNode === targetNode) {
            return false;
        }
        const targetElement = Ext.get(target);
        const sourceElement = Ext.get(data.nodes[0]);
        sourceElement.insertBefore(targetElement);

        this.view.fireEvent('sort', {
            target: targetNode,
            source: sourceNode,
            event: e,
            dd: dd
        });

        return true;
    }
});
