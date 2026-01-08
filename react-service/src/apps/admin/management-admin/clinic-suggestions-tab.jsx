import { useState } from "react";
import { Table, Button, message } from "antd";
// import MergeClinicModal from "@/component/merge-clinic-modal.jsx";

export default function ClinicSuggestionsTab() {
  const [suggestions, setSuggestions] = useState([]);
  const [loading, setLoading] = useState(false);
  const [mergeClinic, setMergeClinic] = useState(null);
  const [openMergeModal, setOpenMergeModal] = useState(false);

  const columns = [
    { title: "ID", dataIndex: "id", key: "id", width: 80 },
    { title: "Tên phòng khám", dataIndex: "name", key: "name", ellipsis: true },
    { title: "Địa chỉ", dataIndex: "address", key: "address", ellipsis: true },
    {
      title: "Hành động",
      key: "action",
      width: 120,
      render: (_, record) => (
        <Button type="primary" size="small" onClick={() => handleMerge(record)}>
          Merge
        </Button>
      ),
    },
  ];

  const handleMerge = (clinic) => {
    setMergeClinic(clinic);
    setOpenMergeModal(true);
  };

  return (
    <div>
      <Table
        rowKey="id"
        columns={columns}
        dataSource={suggestions}
        loading={loading}
        scroll={{ x: 800 }}
      />

      {/* <MergeClinicModal
        open={openMergeModal}
        clinic={mergeClinic}
        onCancel={() => setOpenMergeModal(false)}
        onSubmit={(mergedData) => {
          message.success("Merge thành công");
          setOpenMergeModal(false);
        }}
      /> */}
    </div>
  );
}
