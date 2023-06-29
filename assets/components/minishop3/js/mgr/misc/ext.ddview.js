minishop.DragZone = function (view) {
    this.view = view;
    minishop.DragZone.superclass.constructor.call(this, view.getEl());
};
Ext.extend(minishop.DragZone, Ext.dd.DragZone, {

    getDragData: function (e) {
        var target = e.getTarget(this.view.itemSelector);
        if (!target) {
            return false;
        } else if (!this.view.isSelected(target)) {
            this.view.onClick(e);
        }

        var selNodes = this.view.getSelectedNodes();
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
        return Ext.dd.DropZone.prototype.dropAllowed && (target != data.nodes[0]);
    },

    onNodeDrop: function (target, dd, e, data) {
        var targetNode = this.view.getRecord(target);
        var sourceNode = this.view.getRecord(data.nodes[0]);
        if (sourceNode == targetNode) {
            return false;
        }
        var targetElement = Ext.get(target);
        var sourceElement = Ext.get(data.nodes[0]);
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
