minishop.DragZone = function (view) {
    this.view = view;
    minishop.DragZone.superclass.constructor.call(this, view.getEl());
};
Ext.extend(minishop.DragZone, Ext.dd.DragZone, {

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


minishop.DropZone = function (view) {
    this.view = view;
    minishop.DropZone.superclass.constructor.call(this, view.getEl(), {containerScroll: true});
};
Ext.extend(minishop.DropZone, Ext.dd.DropZone, {

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
