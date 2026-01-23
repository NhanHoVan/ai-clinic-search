import { Modal, Form } from "antd";
import React from "react";

export default function EditClinicModal({
	open,
	loading,
	clinic,
	onCancel,
	onSubmit,
	title,
	okeText,
	cancelText,
	renderForm,
}) {
	const [form] = Form.useForm();
	const initialValuesRef = React.useRef(null);

	React.useEffect(() => {
		if (clinic) {
			const formValues = {
				...clinic,
				specialties: (clinic.specialties || []).map((s) => s.name),
			};

			form.setFieldsValue(formValues);
			initialValuesRef.current = formValues;
		} else {
			form.resetFields();
			initialValuesRef.current = null;
		}
	}, [clinic, form]);

	const handleSubmit = (values) => {
		if (!initialValuesRef.current) {
			onSubmit(values);
			return;
		}

		const isEqual = (a, b) => {
			if (Array.isArray(a) && Array.isArray(b)) {
				return JSON.stringify(a) === JSON.stringify(b);
			}
			return a === b;
		};

		const changedValues = Object.keys(values).reduce((acc, key) => {
			if (!isEqual(values[key], initialValuesRef.current[key])) {
				acc[key] = values[key];
			}
			return acc;
		}, {});

		onSubmit({
			id: clinic.id,
			...changedValues,
		});
	};

	return (
		<Modal
			title={<span style={{ fontSize: 20 }}>{title}</span>}
			open={open}
			onCancel={onCancel}
			onOk={() => form.submit()}
			confirmLoading={loading}
			okText={okeText}
			cancelText={cancelText}
			width={{
				xs: "90%",
				md: "80%",
				lg: "60%",
				xl: "50%",
			}}
			maskClosable={false}
			style={{ top: 50 }}
		>
			{renderForm && renderForm(form, handleSubmit)}
		</Modal>
	);
}
